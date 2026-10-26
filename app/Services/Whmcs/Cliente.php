<?php

namespace App\Services\Whmcs;

use App\Support\ChavesDeIntegracao;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A porta única para a API do WHMCS.
 *
 * O portal não fala HTTP com o financeiro em nenhum outro lugar: quem precisa de serviços,
 * faturas, dados do cliente ou troca de senha chama este serviço. As credenciais vêm do painel
 * (`config('whmcs.*')`, sobreposto por `ChavesDeIntegracao::aplicar()`), e nenhum método daqui
 * aceita id de cliente vindo da requisição — quem resolve o id é o controller, a partir da
 * ficha autenticada. É essa regra que impede um cliente de ler a fatura do outro trocando um
 * número na URL.
 *
 * A degradação é deliberada: sem credencial, `configurado()` diz não e as telas explicam; com
 * o financeiro fora do ar, a chamada volta `null` e a página segue de pé. O único lugar que
 * registra o erro é o log do servidor, com a ação e a mensagem — nunca o segredo, nunca a
 * senha, nunca o parâmetro inteiro.
 */
class Cliente
{
    /** A integração só existe com as três peças: endereço, identificador e segredo. */
    public function configurado(): bool
    {
        return filled(config('whmcs.url'))
            && filled(config('whmcs.identifier'))
            && filled(config('whmcs.secret'));
    }

    /** O que falta para a integração abrir a porta — em nomes que a tela possa mostrar. */
    public function faltando(): array
    {
        return ChavesDeIntegracao::faltantesDoWhmcs();
    }

    /**
     * O id do cliente no WHMCS a partir do e-mail. Serve para a ficha que nasceu no portal
     * encontrar a conta que já existe no financeiro, sem ninguém digitar número.
     */
    public function idDoCliente(string $email): ?int
    {
        if (! $this->configurado() || blank($email)) {
            return null;
        }

        $resposta = $this->chamar('GetClientsDetails', ['email' => $email]);

        if ($resposta === null) {
            return null;
        }

        $id = $resposta['clientid'] ?? $resposta['userid'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * Os serviços/produtos contratados no financeiro, já traduzidos para a tela.
     * `null` é falha de conversa; lista vazia é "não tem nenhum".
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function servicos(int $clientId, ?int $limite = null): ?array
    {
        $resposta = $this->chamar('GetClientsProducts', [
            'clientid' => $clientId,
            'limitnum' => $limite ?? $this->limite(),
        ]);

        if ($resposta === null) {
            return null;
        }

        return collect($this->lista($resposta['products']['product'] ?? []))
            ->map(fn (array $servico): array => [
                'id' => (int) ($servico['id'] ?? 0),
                'nome' => (string) ($servico['name'] ?? $servico['productname'] ?? 'Serviço'),
                'grupo' => (string) ($servico['groupname'] ?? ''),
                'dominio' => (string) ($servico['domain'] ?? ''),
                'status' => (string) ($servico['status'] ?? ''),
                'proximo_vencimento' => $this->data($servico['nextduedate'] ?? null),
                'valor_cents' => $this->centavos($servico['recurringamount'] ?? $servico['totalrecurringamount'] ?? 0),
                'ciclo' => (string) ($servico['billingcycle'] ?? ''),
                'contratado_em' => $this->data($servico['regdate'] ?? null),
                'url' => $this->linkDaArea('productdetails', (int) ($servico['id'] ?? 0)),
            ])
            ->values()
            ->all();
    }

    /**
     * As faturas do cliente, da mais nova para a mais antiga (a API devolve nessa ordem).
     * O link leva à própria fatura no financeiro, onde o cliente paga e tira a segunda via.
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function faturas(int $clientId, ?int $limite = null): ?array
    {
        $resposta = $this->chamar('GetInvoices', [
            'userid' => $clientId,
            'limitnum' => $limite ?? $this->limite(),
        ]);

        if ($resposta === null) {
            return null;
        }

        return collect($this->lista($resposta['invoices']['invoice'] ?? []))
            ->map(fn (array $fatura): array => [
                'id' => (int) ($fatura['id'] ?? 0),
                'numero' => (string) (($fatura['invoicenum'] ?? '') ?: ($fatura['id'] ?? '')),
                'emitida' => $this->data($fatura['date'] ?? null),
                'vencimento' => $this->data($fatura['duedate'] ?? null),
                'paga_em' => $this->data($fatura['datepaid'] ?? null),
                'total_cents' => $this->centavos($fatura['total'] ?? 0),
                'saldo_cents' => $this->centavos($fatura['balance'] ?? 0),
                'status' => (string) ($fatura['status'] ?? ''),
                'metodo' => (string) ($fatura['paymentmethod'] ?? ''),
                'url' => $this->linkDaArea('viewinvoice', (int) ($fatura['id'] ?? 0), 'id'),
            ])
            ->values()
            ->all();
    }

    /**
     * Espelha os dados que o portal deixa o cliente corrigir. E-mail não entra: no WHMCS ele é
     * o login do financeiro, e trocá-lo por aqui poderia trancar a pessoa do lado de lá. Quem
     * muda e-mail de acesso fala com a equipe.
     */
    public function atualizarDados(int $clientId, array $dados): bool
    {
        $nome = trim((string) ($dados['nome'] ?? ''));
        $partes = preg_split('/\s+/', $nome, 2) ?: [];

        $campos = [
            'clientid' => $clientId,
            'firstname' => (string) ($partes[0] ?? ''),
            'lastname' => (string) ($partes[1] ?? ($partes[0] ?? '')),
            'address1' => trim((string) ($dados['endereco'] ?? '')),
            'city' => trim((string) ($dados['cidade'] ?? '')),
            'state' => trim((string) ($dados['uf'] ?? '')),
            'postcode' => trim((string) ($dados['cep'] ?? '')),
            'phonenumber' => trim((string) ($dados['telefone'] ?? '')),
        ];

        if (($dados['tipo'] ?? '') === 'juridica' && $nome !== '') {
            $campos['companyname'] = $nome;
        }

        // Campo em branco no portal não pode apagar o dado do financeiro: só vai o que existe.
        $campos = array_filter($campos, fn ($valor): bool => $valor !== '' && $valor !== null);

        return $this->chamar('UpdateClient', $campos) !== null;
    }

    /** A mesma senha do portal no financeiro, para o cliente não decorar duas. */
    public function atualizarSenha(int $clientId, string $senha): bool
    {
        if ($senha === '') {
            return false;
        }

        return $this->chamar('UpdateClient', [
            'clientid' => $clientId,
            'password2' => $senha,
        ]) !== null;
    }

    /** O rótulo em português de um estado de fatura; desconhecido aparece como veio. */
    public static function rotuloDaFatura(string $status): string
    {
        return (string) (config('whmcs.faturas_status')[$status]['rotulo'] ?? ($status !== '' ? $status : 'Sem situação'));
    }

    public static function tomDaFatura(string $status): string
    {
        return (string) (config('whmcs.faturas_status')[$status]['tom'] ?? 'neutral');
    }

    /** O rótulo em português de um estado de serviço; desconhecido aparece como veio. */
    public static function rotuloDoServico(string $status): string
    {
        return (string) (config('whmcs.servicos_status')[$status]['rotulo'] ?? ($status !== '' ? $status : 'Sem situação'));
    }

    public static function tomDoServico(string $status): string
    {
        return (string) (config('whmcs.servicos_status')[$status]['tom'] ?? 'neutral');
    }

    /** "Monthly" vira "por mês"; ciclo desconhecido aparece como veio. */
    public static function rotuloDoCiclo(string $ciclo): string
    {
        return (string) (config('whmcs.ciclos')[$ciclo] ?? $ciclo);
    }

    /**
     * A chamada crua. Devolve o JSON de sucesso ou `null` — e o `null` é sempre uma falha,
     * nunca um "não tem nada". Quem chama decide o que mostrar, mas nunca recebe exceção de
     * rede: a página do cliente não pode cair porque o financeiro demorou.
     */
    private function chamar(string $acao, array $parametros): ?array
    {
        $url = $this->endpoint();

        if ($url === '') {
            return null;
        }

        $corpo = array_merge([
            'identifier' => (string) config('whmcs.identifier'),
            'secret' => (string) config('whmcs.secret'),
            'action' => $acao,
            'responsetype' => 'json',
        ], $parametros);

        try {
            $resposta = Http::asForm()
                ->acceptJson()
                ->timeout(max(3, (int) config('whmcs.timeout', 12)))
                ->post($url, $corpo);
        } catch (Throwable $e) {
            Log::warning('WHMCS não respondeu.', ['acao' => $acao, 'erro' => $e->getMessage()]);

            return null;
        }

        if (! $resposta->successful()) {
            Log::warning('WHMCS respondeu com erro HTTP.', ['acao' => $acao, 'status' => $resposta->status()]);

            return null;
        }

        $json = $resposta->json();

        if (! is_array($json) || ($json['result'] ?? null) !== 'success') {
            Log::warning('WHMCS recusou a chamada.', [
                'acao' => $acao,
                'mensagem' => is_array($json) ? (string) ($json['message'] ?? 'sem mensagem') : 'resposta não era JSON',
            ]);

            return null;
        }

        return $json;
    }

    /** A raiz do WHMCS, com `/includes/api.php` quando a pessoa informou só o domínio. */
    private function endpoint(): string
    {
        $url = trim((string) config('whmcs.url'));

        if ($url === '') {
            return '';
        }

        $caminho = (string) (parse_url($url, PHP_URL_PATH) ?? '');

        return str_ends_with(strtolower($caminho), '.php')
            ? $url
            : rtrim($url, '/').'/includes/api.php';
    }

    /** O endereço da área do cliente, para onde o botão "abrir no financeiro" leva. */
    private function linkDaArea(string $pagina, int $id, string $parametro = 'id'): string
    {
        $raiz = preg_replace('#(?:/includes)?/[^/]+\.php$#i', '', rtrim(trim((string) config('whmcs.url')), '/'));

        return ($raiz ?: rtrim(trim((string) config('whmcs.url')), '/'))
            .'/clientarea.php?action='.$pagina.'&'.$parametro.'='.$id;
    }

    private function limite(): int
    {
        return max(5, (int) config('whmcs.limite', 50));
    }

    /** A API devolve um objeto quando há um item e uma lista quando há vários. */
    private function lista(mixed $valor): array
    {
        if (! is_array($valor) || $valor === []) {
            return [];
        }

        return array_is_list($valor) ? $valor : [$valor];
    }

    /** "299.00" e "1.234,56" viram centavos; data zerada do MySQL vira `null`. */
    private function centavos(mixed $valor): int
    {
        $texto = trim((string) $valor);

        if (str_contains($texto, ',')) {
            $texto = str_replace(['.', ','], ['', '.'], $texto);
        }

        return (int) round(((float) $texto) * 100);
    }

    private function data(mixed $valor): ?Carbon
    {
        $texto = trim((string) $valor);

        if ($texto === '' || str_starts_with($texto, '0000')) {
            return null;
        }

        try {
            return Carbon::parse($texto);
        } catch (Throwable) {
            return null;
        }
    }
}

<?php

namespace App\Services\Whmcs;

use App\Support\ChavesDeIntegracao;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
 *
 * Duas portas além das telas do cliente: `Veiculacao` filtra o que sai daqui para o portal
 * (estado, grupo e o link externo), e `sonda()` responde à pergunta que a tela de Configurações
 * faz — a `chamar()` não serve para isso porque achata todo erro em `null`, e quem está ligando
 * a integração precisa saber *o que* o financeiro disse.
 */
class Cliente
{
    /** A lista de grupos do financeiro, lida uma vez por instância e reaproveitada. */
    private ?array $grupos = null;

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
     * O endereço exato que será consultado, já com o `/includes/api.php` que a classe completa.
     * A tela de Configurações mostra este valor porque é adivinhação demais pedir à pessoa que
     * confira se o domínio que ela digitou virou a URL certa.
     */
    public function endpointParaExibir(): string
    {
        return $this->endpoint();
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
     * A ficha do cliente como o financeiro a escreve: nome, contato, endereço, documento,
     * nascimento e o acesso à plataforma de rastreamento.
     *
     * `null` é falha de conversa, nunca "ficha vazia". Quem chama decide o que fazer com o
     * resultado — e a regra da casa é gravar só o que está em branco no CRM, porque o que a
     * equipe digitou manda e o financeiro é a fonte do que ninguém preencheu.
     *
     * Os campos personalizados chegam da API sem nome, só com número: o mapa mora em
     * `config('whmcs.campos')` (sobreposto pelo painel), porque quem sabe qual número é o CPF é a
     * instalação do financeiro, não a documentação do WHMCS.
     *
     * @return array<string, mixed>|null
     */
    public function ficha(int $clientId): ?array
    {
        $resposta = $this->chamar('GetClientsDetails', ['clientid' => $clientId, 'stats' => false]);

        if ($resposta === null) {
            return null;
        }

        // As instalações devolvem os dados planos e uma cópia dentro de `client`; ler a cópia
        // quando ela existe evita depender de qual das duas formas a versão do WHMCS devolve.
        $cliente = is_array($resposta['client'] ?? null) ? $resposta['client'] : $resposta;

        $campos = $this->camposPersonalizados($cliente);

        $documento = trim((string) ($campos[$this->campoPersonalizado('documento')] ?? ''));
        $documento = $documento !== '' ? $documento : trim((string) ($cliente['tax_id'] ?? ''));

        $endereco = array_filter([
            trim((string) ($cliente['address1'] ?? '')),
            trim((string) ($cliente['address2'] ?? '')),
        ]);

        return [
            'nome' => trim((string) ($cliente['fullname']
                ?? trim(($cliente['firstname'] ?? '').' '.($cliente['lastname'] ?? '')))),
            'email' => mb_strtolower(trim((string) ($cliente['email'] ?? ''))),
            'telefone' => $this->telefone($cliente['phonenumber'] ?? $cliente['telephoneNumber'] ?? ''),
            'documento' => $documento,
            'nascimento' => $this->dataDeNascimento($campos[$this->campoPersonalizado('nascimento')] ?? null),
            'endereco' => implode(' - ', $endereco),
            'cidade' => trim((string) ($cliente['city'] ?? '')),
            'uf' => mb_strtoupper(trim((string) ($cliente['state'] ?? ''))),
            'cep' => trim((string) ($cliente['postcode'] ?? '')),
            'plataforma' => trim((string) ($campos[$this->campoPersonalizado('plataforma')] ?? '')),
            'usuario_plataforma' => trim((string) ($campos[$this->campoPersonalizado('usuario')] ?? '')),
        ];
    }

    /**
     * O cadastro inteiro que o financeiro mantém, do jeito que a equipe quer ler na ficha.
     *
     * `ficha()` existe para preencher o CRM — ela devolve só o que a ficha tem onde guardar.
     * Este método é o outro lado: mostra tudo o que o financeiro sabe, inclusive o que o CRM não
     * guarda (grupo do cliente, situação, endereço partido, crédito, último acesso, os campos
     * personalizados que ninguém mapeou). Nada disto é copiado para a base local: a ficha lê na
     * hora e desenha, porque a fonte continua sendo o financeiro.
     *
     * `null` é falha de conversa, nunca "cadastro vazio".
     *
     * @return array<string, mixed>|null
     */
    public function cadastro(int $clientId): ?array
    {
        $resposta = $this->chamar('GetClientsDetails', ['clientid' => $clientId, 'stats' => false]);

        if ($resposta === null) {
            return null;
        }

        $cliente = is_array($resposta['client'] ?? null) ? $resposta['client'] : $resposta;
        $campos = $this->camposPersonalizados($cliente);
        $mapeados = array_filter([
            $this->campoPersonalizado('documento'),
            $this->campoPersonalizado('nascimento'),
            $this->campoPersonalizado('plataforma'),
            $this->campoPersonalizado('usuario'),
        ]);

        $documento = trim((string) ($campos[$this->campoPersonalizado('documento')] ?? ''));
        $documento = $documento !== '' ? $documento : trim((string) ($cliente['tax_id'] ?? ''));

        // Campo que o mapa não conhece continua sendo dado do cliente: some do cadastro só
        // quando está vazio. O número vai junto porque é a única identidade que o WHMCS dá.
        $extras = [];
        foreach ($campos as $id => $valor) {
            if ($valor === '' || in_array($id, $mapeados, true) || in_array($id, [8, 249], true)) {
                continue;
            }

            $extras[] = ['id' => $id, 'valor' => $valor];
        }

        return [
            'id' => $clientId,
            'nome' => trim((string) ($cliente['fullname']
                ?? trim(($cliente['firstname'] ?? '').' '.($cliente['lastname'] ?? '')))),
            'empresa' => trim((string) ($cliente['companyname'] ?? '')),
            'email' => mb_strtolower(trim((string) ($cliente['email'] ?? ''))),
            'telefone' => $this->telefone($cliente['phonenumber'] ?? $cliente['telephoneNumber'] ?? ''),
            'documento' => $documento,
            'nascimento' => $this->dataDeNascimento($campos[$this->campoPersonalizado('nascimento')] ?? null),
            'endereco' => trim((string) ($cliente['address1'] ?? '')),
            'complemento' => trim((string) ($cliente['address2'] ?? '')),
            'cidade' => trim((string) ($cliente['city'] ?? '')),
            'uf' => mb_strtoupper(trim((string) ($cliente['state'] ?? ''))),
            'cep' => trim((string) ($cliente['postcode'] ?? '')),
            'pais' => trim((string) ($cliente['countryname'] ?? $cliente['country'] ?? '')),
            'grupo' => $this->grupoDoCliente((int) ($cliente['groupid'] ?? 0)),
            'situacao' => trim((string) ($cliente['status'] ?? '')),
            'idioma' => trim((string) ($cliente['language'] ?? '')),
            'moeda' => trim((string) ($cliente['currency_code'] ?? '')),
            'credito_cents' => $this->centavos($cliente['credit'] ?? 0),
            'desde' => $this->data($cliente['datecreated'] ?? null),
            'ultimo_acesso' => $this->ultimoAcesso($cliente['lastlogin'] ?? null),
            'plataforma' => trim((string) ($campos[$this->campoPersonalizado('plataforma')] ?? '')),
            'usuario_plataforma' => trim((string) ($campos[$this->campoPersonalizado('usuario')] ?? '')),
            'nfe' => mb_strtoupper(trim((string) ($campos[8] ?? ''))) === 'NFE',
            'nota_fiscal' => trim((string) ($campos[249] ?? '')),
            'observacoes' => trim((string) ($cliente['notes'] ?? '')),
            'campos_extras' => $extras,
        ];
    }

    /**
     * Os serviços/produtos contratados no financeiro, já traduzidos para a tela.
     * `null` é falha de conversa; lista vazia é "não tem nenhum".
     *
     * O filtro de veiculação acontece aqui, depois de a API responder: `Terminated` continua
     * existindo lá, o que a decisão da casa diz é se ele ainda merece um lugar na vitrine do
     * cliente. Filtrar antes de chamar seria inventar um parâmetro que a API não tem.
     *
     * O painel chama com `$filtrarVeiculacao = false`: a equipe precisa ver o contrato encerrado
     * e o grupo escondido do cliente — é justamente deles que ela fala ao atender.
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function servicos(int $clientId, ?int $limite = null, bool $filtrarVeiculacao = true): ?array
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
                'servidor' => (string) ($servico['servername'] ?? ''),
                'status' => (string) ($servico['status'] ?? ''),
                'proximo_vencimento' => $this->data($servico['nextduedate'] ?? null),
                'valor_cents' => $this->centavos($servico['recurringamount'] ?? $servico['totalrecurringamount'] ?? 0),
                'ciclo' => (string) ($servico['billingcycle'] ?? ''),
                'contratado_em' => $this->data($servico['regdate'] ?? null),
                'url' => $this->linkDaArea('productdetails', (int) ($servico['id'] ?? 0)),
            ])
            ->filter(fn (array $servico): bool => ! $filtrarVeiculacao
                || (Veiculacao::mostraServico($servico['status']) && Veiculacao::mostraGrupo($servico['grupo'])))
            ->values()
            ->all();
    }

    /**
     * As faturas do cliente, da mais nova para a mais antiga (a API devolve nessa ordem).
     * O link leva à própria fatura no financeiro, onde o cliente paga e tira a segunda via.
     *
     * Como em `servicos()`, o painel chama sem o filtro: fatura paga e cancelada também é
     * histórico que a equipe consulta.
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function faturas(int $clientId, ?int $limite = null, bool $filtrarVeiculacao = true): ?array
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
            ->filter(fn (array $fatura): bool => ! $filtrarVeiculacao || Veiculacao::mostraFatura($fatura['status']))
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

    /** A situação da conta no financeiro — distinta da do CRM e da do serviço. */
    public static function rotuloDoCliente(string $status): string
    {
        return (string) (config('whmcs.cliente_status')[$status]['rotulo'] ?? ($status !== '' ? $status : 'Sem situação'));
    }

    public static function tomDoCliente(string $status): string
    {
        return (string) (config('whmcs.cliente_status')[$status]['tom'] ?? 'neutral');
    }

    /** O idioma da conta no financeiro, na língua da tela. */
    public static function rotuloDoIdioma(string $idioma): string
    {
        return (string) (config('whmcs.idiomas')[$idioma] ?? $idioma);
    }

    /**
     * A chamada crua. Devolve o JSON de sucesso ou `null` — e o `null` é sempre uma falha,
     * nunca um "não tem nada". Quem chama decide o que mostrar, mas nunca recebe exceção de
     * rede: a página do cliente não pode cair porque o financeiro demorou.
     */
    private function chamar(string $acao, array $parametros): ?array
    {
        $sonda = $this->chamarCrua($acao, $parametros);

        if ($sonda['estado'] === 'ok') {
            return $sonda['resposta'];
        }

        if ($sonda['estado'] !== 'sem_credencial') {
            Log::warning('WHMCS não atendeu.', ['acao' => $acao, 'estado' => $sonda['estado'], 'detalhe' => $sonda['detalhe']]);
        }

        return null;
    }

    /**
     * A mesma chamada, sem o filtro de sucesso — a forma que a tela de Configurações precisa
     * para responder em português *o que* o financeiro disse. `chamar()` achata tudo em `null`
     * porque a página do cliente não tem o que fazer com a diferença entre credencial inválida
     * e servidor mudo; quem está ligando a integração tem, e é por isso que os estados e a
     * mensagem original continuam acessíveis aqui.
     *
     * Nenhum caminho deste método escreve segredo: a mensagem vem do WHMCS ou da exceção de
     * rede, e o corpo da requisição não é repassado a lugar nenhum.
     *
     * @return array{estado: string, resposta: array<string, mixed>|null, status: int|null, detalhe: string}
     */
    public function chamarCrua(string $acao, array $parametros): array
    {
        $url = $this->endpoint();

        if ($url === '' || blank(config('whmcs.identifier')) || blank(config('whmcs.secret'))) {
            return ['estado' => 'sem_credencial', 'resposta' => null, 'status' => null, 'detalhe' => 'Falta endereço, identificador ou segredo.'];
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
            return ['estado' => 'sem_resposta', 'resposta' => null, 'status' => null, 'detalhe' => $e->getMessage()];
        }

        if (! $resposta->successful()) {
            return ['estado' => 'http_erro', 'resposta' => null, 'status' => $resposta->status(), 'detalhe' => 'HTTP '.$resposta->status()];
        }

        $json = $resposta->json();

        if (! is_array($json)) {
            return ['estado' => 'recusada', 'resposta' => null, 'status' => $resposta->status(), 'detalhe' => 'A resposta não é JSON.'];
        }

        // O campo `result` é comparado sem caixa de propósito: o WHMCS responde `SUCCESS` e
        // `FAILURE` em letra maiúscula, e uma comparação exata por `success` jogaria no chão toda
        // conversa bem-sucedida — o financeiro diria que está no ar e o portal veria uma falha.
        if (Str::lower((string) ($json['result'] ?? '')) !== 'success') {
            return ['estado' => 'recusada', 'resposta' => $json, 'status' => $resposta->status(), 'detalhe' => (string) ($json['message'] ?? 'sem mensagem')];
        }

        return ['estado' => 'ok', 'resposta' => $json, 'status' => $resposta->status(), 'detalhe' => (string) ($json['message'] ?? '')];
    }

    /**
     * A prova de fogo que a tela de Configurações oferece: pergunta ao financeiro por um e-mail
     * e devolve o que aconteceu, em português, sem meio-termo. Usa `GetClientsDetails`, a única
     * ação que existe para conferir credencial *e* leitura ao mesmo tempo — se a resposta vier,
     * o identificador e o segredo estão certos.
     *
     * @return array{estado: string, titulo: string, texto: string, id: int|null}
     */
    public function sonda(string $email): array
    {
        if (! $this->configurado()) {
            return [
                'estado' => 'sem_credencial',
                'titulo' => 'Ainda não há o que testar.',
                'texto' => 'Preencha o endereço, o identificador e o segredo do WHMCS nas Chaves de integração; só então a conexão pode ser sondada.',
                'id' => null,
            ];
        }

        $sonda = $this->chamarCrua('GetClientsDetails', ['email' => $email]);

        // "Client Not Found" não é credencial recusada: é o WHMCS tendo autenticado a chamada e
        // respondido que aquele e-mail não tem ficha. Algumas instalações devolvem `success` com
        // `clientid` vazio, outras devolvem este erro — as duas significam a mesma coisa, e a
        // pessoa que acabou de configurar o painel precisa ouvir "a porta abre, a ficha não".
        if ($sonda['estado'] === 'recusada' && str_contains(Str::lower($sonda['detalhe']), 'client not found')) {
            return $this->descreveSemConta($email);
        }

        return match ($sonda['estado']) {
            'sem_resposta' => [
                'estado' => 'sem_resposta',
                'titulo' => 'O financeiro não respondeu.',
                'texto' => 'Nenhum retorno chegou do endereço configurado (timeout, DNS ou barreira da hospedagem). Confirme o endereço informado nas Chaves de integração e se o servidor permite conexões de saída.'.($sonda['detalhe'] !== '' ? ' Detalhe: '.$sonda['detalhe'].'.' : ''),
                'id' => null,
            ],
            'http_erro' => [
                'estado' => 'http_erro',
                'titulo' => 'O financeiro respondeu com erro.',
                'texto' => 'A URL da API devolveu '.$sonda['detalhe'].'. Confira se o endereço informado é o da instalação e se o arquivo da API existe (o painel completa /includes/api.php quando você informa só o domínio).',
                'id' => null,
            ],
            'recusada' => [
                'estado' => 'recusada',
                'titulo' => 'O financeiro recusou a chamada.',
                'texto' => 'Ele respondeu, e o motivo veio junto: "'.$sonda['detalhe'].'". Credencial revogada, permissão desligada em Configurações do sistema → API ou endereço errado costumam ser isso.',
                'id' => null,
            ],
            default => $this->descreveSondaBem($sonda['resposta'], $email),
        };
    }

    /**
     * `success` no `result` não significa conta encontrada: o WHMCS responde sucesso com
     * `clientid` vazio quando o e-mail não existe lá. É a diferença entre "a conexão funciona"
     * e "esta pessoa tem ficha no financeiro", e o administrador precisa das duas frases.
     *
     * @param  array<string, mixed>|null  $resposta
     * @return array{estado: string, titulo: string, texto: string, id: int|null}
     */
    private function descreveSondaBem(?array $resposta, string $email): array
    {
        $id = $resposta['clientid'] ?? $resposta['userid'] ?? null;

        if (! is_numeric($id) || (int) $id <= 0) {
            return $this->descreveSemConta($email);
        }

        return [
            'estado' => 'ok',
            'titulo' => 'A conexão está no ar.',
            'texto' => 'O financeiro respondeu e confirmou a conta '.$email.' como cliente '.(int) $id.'. Serviços e faturas já podem ser lidos no portal.',
            'id' => (int) $id,
        ];
    }

    /**
     * A frase das duas formas de "não tem ficha": o `success` com `clientid` vazio e o erro
     * `Client Not Found`. Nas duas o financeiro autenticou a chamada, então o recado é sobre o
     * cadastro daquela pessoa, nunca sobre a credencial do painel.
     *
     * @return array{estado: string, titulo: string, texto: string, id: int|null}
     */
    private function descreveSemConta(string $email): array
    {
        return [
            'estado' => 'sem_conta',
            'titulo' => 'Conexão ok — conta, não.',
            'texto' => 'O financeiro respondeu à chamada, então identificador e segredo estão certos. Ele não acha ficha nenhuma para '.$email.', que é o caso de quem cadastrou no portal com outro e-mail.',
            'id' => null,
        ];
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

    /**
     * O endereço da área do cliente, para onde o botão "abrir no financeiro" leva. Vazio quando
     * a veiculação manda não tirar o cliente do portal — a view só pinta o botão se houver link.
     */
    private function linkDaArea(string $pagina, int $id, string $parametro = 'id'): string
    {
        if (! Veiculacao::mostraLink()) {
            return '';
        }

        $raiz = preg_replace('#(?:/includes)?/[^/]+\.php$#i', '', rtrim(trim((string) config('whmcs.url')), '/'));

        return ($raiz ?: rtrim(trim((string) config('whmcs.url')), '/'))
            .'/clientarea.php?action='.$pagina.'&'.$parametro.'='.$id;
    }

    /** Os campos personalizados da ficha, por número — a única forma que a API devolve. */
    private function camposPersonalizados(array $cliente): array
    {
        $mapa = [];

        foreach ($this->lista($cliente['customfields'] ?? []) as $campo) {
            if (is_array($campo) && isset($campo['id'])) {
                $mapa[(int) $campo['id']] = trim((string) ($campo['value'] ?? ''));
            }
        }

        return $mapa;
    }

    /** O número de um campo personalizado; zero quando o painel não mapeou aquele campo. */
    private function campoPersonalizado(string $nome): int
    {
        return (int) config("whmcs.campos.{$nome}", 0);
    }

    /**
     * O telefone do financeiro no formato que a ficha usa. Ele guarda DDD + número, às vezes com
     * o 55 do país; a tela mostra "(11) 94222-8599". Número que não é brasileiro fica como veio,
     * sem máscara inventada.
     */
    private function telefone(mixed $valor): string
    {
        $digitos = (string) preg_replace('/\D/', '', (string) $valor);

        if (in_array(strlen($digitos), [12, 13], true) && str_starts_with($digitos, '55')) {
            $digitos = substr($digitos, 2);
        }

        return match (strlen($digitos)) {
            11 => '('.substr($digitos, 0, 2).') '.substr($digitos, 2, 5).'-'.substr($digitos, 7),
            10 => '('.substr($digitos, 0, 2).') '.substr($digitos, 2, 4).'-'.substr($digitos, 6),
            default => $digitos,
        };
    }

    /**
     * A data de nascimento nos formatos que o financeiro escreve — `08/01/1937`, `1937-01-08` ou
     * `08011937`. Formato ambíguo lido "no chute" viraria aniversário trocado na ficha, então a
     * leitura é explícita e só vale se a data voltar escrita exatamente como veio.
     */
    private function dataDeNascimento(mixed $valor): ?Carbon
    {
        $texto = trim((string) $valor);

        if ($texto === '') {
            return null;
        }

        foreach (['d/m/Y', 'd-m-Y', 'dmY', 'Y-m-d'] as $formato) {
            $data = Carbon::createFromFormat('!'.$formato, $texto);

            if ($data !== false && $data->format($formato) === $texto) {
                return $data->year >= 1900 && ! $data->greaterThan(now()) ? $data : null;
            }
        }

        return null;
    }

    /**
     * O nome do grupo de clientes que a ficha do financeiro aponta. O cadastro devolve só o
     * número; a lista vem de `GetClientGroups`, uma vez por instância — a ficha inteira precisa
     * de no máximo um nome, e a mesma página não vai perguntar duas vezes.
     */
    private function grupoDoCliente(int $groupId): string
    {
        if ($groupId <= 0) {
            return '';
        }

        if ($this->grupos === null) {
            $resposta = $this->chamar('GetClientGroups', ['limitnum' => 250]);

            $this->grupos = [];

            foreach ($this->lista(is_array($resposta) ? ($resposta['groups']['group'] ?? []) : []) as $grupo) {
                if (is_array($grupo) && isset($grupo['id'])) {
                    $this->grupos[(int) $grupo['id']] = trim((string) ($grupo['groupname'] ?? ''));
                }
            }
        }

        return $this->grupos[$groupId] ?? '';
    }

    /**
     * O último acesso não vem como data: o financeiro devolve o texto da própria tela, do tipo
     * `Date: 29/10/2026 12:34<br>IP Address: ...`. A leitura é explícita e tolerante — o que não
     * bater no formato volta nulo, nunca uma data inventada.
     */
    private function ultimoAcesso(mixed $valor): ?Carbon
    {
        $texto = trim((string) $valor);

        if ($texto === '' || preg_match('/(\d{2}\/\d{2}\/\d{4})(?:\s+(\d{2}:\d{2}))?/', $texto, $achado) !== 1) {
            return null;
        }

        $data = Carbon::createFromFormat('!d/m/Y H:i', $achado[1].' '.($achado[2] ?? '00:00'));

        return $data !== false && ! $data->greaterThan(now()) ? $data : null;
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

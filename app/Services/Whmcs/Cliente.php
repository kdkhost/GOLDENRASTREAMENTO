<?php

namespace App\Services\Whmcs;

use App\Support\ChavesDeIntegracao;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
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

    /**
     * A moeda da conta, pelo número que a API devolve, lida do próprio cadastro.
     *
     * `GetTransactions` escreve `currency: 1` e não existe ação de moedas nesta instalação
     * (`GetCurrencies` responde 403). O único par número→código que a ficha pode provar é o da
     * conta consultada, que `GetClientsDetails` devolve das duas formas. Por isso ele fica guardado
     * quando o cadastro é lido: uma lista lida sem cadastro antes não mostra moeda nenhuma —
     * ausência honesta vale mais do que um código chutado.
     */
    private array $moedas = [];

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
        $resposta = $this->chamar('GetClientsDetails', ['clientid' => $clientId, 'stats' => true]);

        if ($resposta === null) {
            return null;
        }

        $cliente = is_array($resposta['client'] ?? null) ? $resposta['client'] : $resposta;

        // A conta pedida é a conta desenhada: se a resposta apontar para outro número, a ficha
        // inteira fica muda em vez de mostrar o cadastro de outra pessoa. É a primeira barreira
        // contra vazamento — e a única que depende do financeiro, não da ficha local.
        $idNaResposta = $resposta['clientid'] ?? $resposta['client_id'] ?? $resposta['id'] ?? $cliente['id'] ?? null;

        if (is_numeric($idNaResposta) && (int) $idNaResposta !== $clientId) {
            Log::warning('WHMCS devolveu uma conta diferente da consultada.', ['conta' => $clientId]);

            return null;
        }

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

        // A moeda da conta é a única tradução que a ficha pode provar para o número que as
        // transações devolvem; ela é guardada aqui porque é daqui que o financeiro a anuncia.
        $codigoDaMoeda = mb_strtoupper(trim((string) ($cliente['currency_code'] ?? '')));
        $this->moedas = $codigoDaMoeda === '' ? [] : [(int) ($cliente['currency'] ?? 0) => $codigoDaMoeda];

        return [
            'id' => $clientId,
            'userid' => (int) ($cliente['userid'] ?? 0),
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
            'moeda' => $codigoDaMoeda,
            'moeda_id' => (int) ($cliente['currency'] ?? 0),
            'credito_cents' => $this->centavos($cliente['credit'] ?? 0),
            'desde' => $this->data($cliente['datecreated'] ?? null),
            'ultimo_acesso' => $this->ultimoAcesso($cliente['lastlogin'] ?? null),
            'ultimo_ip' => $this->ipDoUltimoAcesso($cliente['lastlogin'] ?? null),
            'plataforma' => trim((string) ($campos[$this->campoPersonalizado('plataforma')] ?? '')),
            'usuario_plataforma' => trim((string) ($campos[$this->campoPersonalizado('usuario')] ?? '')),
            'nfe' => mb_strtoupper(trim((string) ($campos[8] ?? ''))) === 'NFE',
            'nota_fiscal' => trim((string) ($campos[249] ?? '')),
            'observacoes' => trim((string) ($cliente['notes'] ?? '')),
            'campos_extras' => $extras,

            // A situação da conta além do que o CRM guarda. Cada sinal é `null` quando o
            // financeiro não devolveu o campo — a tela não transforma ausência em "não".
            'isento_de_imposto' => $this->sinal($cliente['taxexempt'] ?? null),
            'forma_de_pagamento' => trim((string) ($cliente['defaultgateway'] ?? '')),
            'contato_de_cobranca' => array_key_exists('billingcid', $cliente) ? (int) $cliente['billingcid'] : null,
            'email_verificado' => $this->sinal($cliente['email_verified'] ?? null),
            'cobranca_automatica' => array_key_exists('disableautocc', $cliente) ? ! (bool) $cliente['disableautocc'] : null,
            'faturas_separadas' => $this->sinal($cliente['separateinvoices'] ?? null),
            'juros_por_atraso' => array_key_exists('latefeeoveride', $cliente) ? ! (bool) $cliente['latefeeoveride'] : null,
            'avisos_de_vencimento' => array_key_exists('overideduenotices', $cliente) ? ! (bool) $cliente['overideduenotices'] : null,
            'recebe_emails' => array_key_exists('emailoptout', $cliente) ? ! (bool) $cliente['emailoptout'] : null,
            'recebe_marketing' => array_key_exists('marketing_emails_opt_in', $cliente) ? $this->sinal($cliente['marketing_emails_opt_in']) : null,
            'preferencias_de_email' => $this->preferenciasDeEmail($cliente['email_preferences'] ?? null),
            'cartao_de_reserva' => $this->cartaoDeReserva($cliente),
            'login_unico' => $this->sinal($cliente['allowSingleSignOn'] ?? null),
            'usuarios' => $this->usuariosDaConta($cliente['users'] ?? null),
            'resumo' => $this->resumoDoFinanceiro($resposta['stats'] ?? null),
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

        return $resposta === null ? null : $this->lerServicos($resposta, $clientId, $filtrarVeiculacao);
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

        return $resposta === null ? null : $this->lerFaturas($resposta, $clientId, $filtrarVeiculacao);
    }

    /**
     * O histórico inteiro da conta, lido de uma vez: serviços, adicionais, domínios, faturas,
     * transações, créditos, pedidos, contatos e chamados.
     *
     * É a leitura da ficha do painel, e obedece a uma regra só: **cada consulta pede a conta
     * daquela ficha, e cada item conferido antes de entrar na tela.** `GetOrders` e `GetContacts`
     * respondem também sem o recado de quem é o dono — conferido na produção, onde a consulta solta
     * devolveu pedido e contato de outras contas. Filtrar o item não é paranoia de sintaxe: é o
     * único freio quando o financeiro resolve ignorar um parâmetro.
     *
     * Por isso pedido e contato só são consultados com o número de usuário que o próprio cadastro
     * devolveu. Sem ele, a seção volta `null` — "não foi lida", nunca "não tem nenhuma".
     *
     * As consultas saem em lote, todas ao mesmo tempo: com o financeiro respondendo, a página espera
     * o tempo de uma chamada, não de nove. E nada daqui roda quando o cadastro falhou — quem chama
     * decide isso antes.
     *
     * @return array<string, array<int, array<string, mixed>>|null>
     */
    public function historico(int $clientId, int $userId): array
    {
        $limite = $this->limite();

        $chamadas = [
            'servicos' => ['acao' => 'GetClientsProducts', 'parametros' => ['clientid' => $clientId, 'limitnum' => $limite]],
            'faturas' => ['acao' => 'GetInvoices', 'parametros' => ['userid' => $clientId, 'limitnum' => $limite]],
            'transacoes' => ['acao' => 'GetTransactions', 'parametros' => ['clientid' => $clientId, 'limitnum' => $limite]],
            'creditos' => ['acao' => 'GetCredits', 'parametros' => ['clientid' => $clientId, 'limitnum' => $limite]],
            'addons' => ['acao' => 'GetClientsAddons', 'parametros' => ['clientid' => $clientId]],
            'dominios' => ['acao' => 'GetClientsDomains', 'parametros' => ['clientid' => $clientId, 'limitnum' => $limite]],
            'chamados' => ['acao' => 'GetTickets', 'parametros' => ['clientid' => $clientId, 'limitnum' => $limite]],
            // Conferido na produção: `GetQuotes` ignora `clientid` e devolve a base inteira. Os dois
            // recados vão porque se ignora um não se sabe qual, e o filtro por item é o que decide.
            'orcamentos' => ['acao' => 'GetQuotes', 'parametros' => ['userid' => $clientId, 'clientid' => $clientId, 'limitnum' => $limite]],
        ];

        if ($userId > 0) {
            $chamadas['pedidos'] = ['acao' => 'GetOrders', 'parametros' => ['userid' => $userId, 'limitnum' => $limite]];
            $chamadas['contatos'] = ['acao' => 'GetContacts', 'parametros' => ['userid' => $userId, 'limitnum' => $limite]];
        }

        $respostas = $this->chamarEmLote($chamadas);

        $leituras = [
            'servicos' => fn (array $r): ?array => $this->lerServicos($r, $clientId, false),
            'faturas' => fn (array $r): ?array => $this->lerFaturas($r, $clientId, false),
            'transacoes' => fn (array $r): ?array => $this->lerTransacoes($r, $clientId),
            'creditos' => fn (array $r): ?array => $this->lerCreditos($r, $clientId),
            'addons' => fn (array $r): ?array => $this->lerAddons($r, $clientId),
            'dominios' => fn (array $r): ?array => $this->lerDominios($r, $clientId),
            'chamados' => fn (array $r): ?array => $this->lerChamados($r, $clientId),
            'orcamentos' => fn (array $r): ?array => $this->lerOrcamentos($r, $clientId),
            'pedidos' => fn (array $r): ?array => $this->lerPedidos($r, $userId),
            'contatos' => fn (array $r): ?array => $this->lerContatos($r, $userId),
        ];

        $historico = [];

        foreach ($leituras as $nome => $ler) {
            $resposta = $respostas[$nome] ?? null;
            $historico[$nome] = $resposta === null ? null : $ler($resposta);
        }

        return $historico;
    }

    /*
    |--------------------------------------------------------------------------
    | As peças que toda leitura usa
    |--------------------------------------------------------------------------
    */

    /**
     * Todas as consultas do histórico em um lote só.
     *
     * O `Http::pool` dispara as requisições juntas, então a ficha espera o tempo da mais lenta em
     * vez da soma de dez, e um prazo cobre o lote inteiro. A resposta chega pelo nome dado em
     * `->as()`; o que falhou — timeout, HTTP de erro, chamada recusada — volta `null` já escrito no
     * log, porque leitor nenhum pode confundir "não respondeu" com "não tem nada".
     *
     * @param  array<string, array{acao: string, parametros: array<string, mixed>}>  $chamadas
     * @return array<string, array<string, mixed>|null>
     */
    private function chamarEmLote(array $chamadas): array
    {
        $url = $this->endpoint();

        if ($url === '' || blank(config('whmcs.identifier')) || blank(config('whmcs.secret'))) {
            return array_fill_keys(array_keys($chamadas), null);
        }

        $prazo = max(3, (int) config('whmcs.timeout', 12));
        $nomes = array_keys($chamadas);

        // Cinco portas ao mesmo tempo: o suficiente para o lote não virar dez esperas em fila sem
        // tomar a conexão do financeiro de uma pessoa que está pagando uma fatura agora.
        $respostas = Http::pool(fn (Pool $lote): array => collect($chamadas)
            ->map(fn (array $chamada, string $nome) => $lote->as($nome)
                ->asForm()
                ->acceptJson()
                ->timeout($prazo)
                ->post($url, $this->corpo($chamada['acao'], $chamada['parametros'])))
            ->values()
            ->all(), 5);

        // A lista vem por posição, mas quem consome procura pelo nome da consulta — devolver
        // indexada por número faria toda seção parecer "não lida", mesmo com resposta boa.
        $interpretadas = [];

        foreach ($nomes as $indice => $nome) {
            $interpretadas[$nome] = $this->interpretarLote(
                $respostas[$nome] ?? $respostas[$indice] ?? null,
                (string) ($chamadas[$nome]['acao'] ?? 'lote'),
            );
        }

        return $interpretadas;
    }

    /**
     * A resposta de uma chamada do lote, do mesmo jeito que `chamarCrua()` lê uma chamada só:
     * `null` é falha, nunca lista vazia. O par `estado`/`detalhe` fica de fora porque aqui quem
     * consome é a ficha, que só sabe "não consegui perguntar" — a tela de Configurações é que
     * precisa do motivo, e ela usa `chamarCrua()`.
     */
    private function interpretarLote(mixed $resposta, string $acao): ?array
    {
        if ($resposta instanceof Throwable) {
            Log::warning('WHMCS não atendeu.', ['acao' => $acao, 'estado' => 'sem_resposta', 'detalhe' => $resposta->getMessage()]);

            return null;
        }

        if (! $resposta instanceof Response) {
            return null;
        }

        if (! $resposta->successful()) {
            Log::warning('WHMCS não atendeu.', ['acao' => $acao, 'estado' => 'http_erro', 'detalhe' => 'HTTP '.$resposta->status()]);

            return null;
        }

        $json = $resposta->json();

        if (! is_array($json)) {
            Log::warning('WHMCS não atendeu.', ['acao' => $acao, 'estado' => 'recusada', 'detalhe' => 'a resposta não é JSON']);

            return null;
        }

        if (Str::lower((string) ($json['result'] ?? '')) !== 'success') {
            Log::warning('WHMCS não atendeu.', ['acao' => $acao, 'estado' => 'recusada', 'detalhe' => (string) ($json['message'] ?? 'sem mensagem')]);

            return null;
        }

        return $json;
    }

    /** O corpo de qualquer chamada: as credenciais do painel, a ação e o JSON como resposta. */
    private function corpo(string $acao, array $parametros): array
    {
        return array_merge([
            'identifier' => (string) config('whmcs.identifier'),
            'secret' => (string) config('whmcs.secret'),
            'action' => $acao,
            'responsetype' => 'json',
        ], $parametros);
    }

    /**
     * De quem a resposta fala.
     *
     * `true` é a resposta escrevendo o número da conta consultada; `false` é ela nomeando outra
     * conta, caso em que a lista inteira fica fora da tela; `null` é a resposta não dizer nada. Só
     * o `true` autoriza um item que não declara dono — o extrato de crédito é assim — a entrar na
     * ficha. Nenhuma lista desenhada vem de uma conta que o financeiro não confirmou.
     */
    private function contaConfirmada(array $resposta, int $clientId): ?bool
    {
        foreach (['clientid', 'client_id', 'userid'] as $chave) {
            if (! array_key_exists($chave, $resposta) || ! is_numeric($resposta[$chave])) {
                continue;
            }

            if ((int) $resposta[$chave] === $clientId) {
                return true;
            }

            Log::warning('WHMCS respondeu a lista de outra conta.', ['conta' => $clientId, 'chave' => $chave]);

            return false;
        }

        return null;
    }

    /**
     * O item é da conta da ficha?
     *
     * Há duas políticas, e a diferença entre elas é o que a API faz de verdade — não o que a
     * documentação promete:
     *
     * - **Consulta que filtra por conta** (`GetClientsProducts`, `GetClientsAddons`,
     *   `GetClientsDomains`, `GetInvoices`, `GetTransactions`, `GetCredits`, `GetTickets`): o item
     *   que declara um dono precisa ser daquele dono; o que não declara nada entra, porque a
     *   própria consulta já pediu aquela conta e o extrato de crédito, por exemplo, não escreve o
     *   número em item nenhum.
     * - **Consulta que responde sem dono** (`GetOrders`, `GetContacts`) ou que **ignora o filtro**
     *   (`GetQuotes` — conferido na produção, onde a consulta por uma conta devolveu a base
     *   inteira): aqui o item que não declara dono fica de fora. Não há outra barreira.
     *
     * Nos dois casos, item dizendo outro número é descartado, e a resposta que nomeia outra conta
     * derruba a lista inteira antes (`contaConfirmada()`).
     *
     * @param  bool  $itemSemDonoEntra  a política da consulta, não do item
     */
    private function pertenceAo(array $item, int $dueno, bool $itemSemDonoEntra = false): bool
    {
        if ($dueno <= 0) {
            return false;
        }

        foreach (['clientid', 'client_id', 'userid', 'cid'] as $chave) {
            if (array_key_exists($chave, $item) && is_numeric($item[$chave])) {
                return (int) $item[$chave] === $dueno;
            }
        }

        return $itemSemDonoEntra;
    }

    /*
    |--------------------------------------------------------------------------
    | Os leitores de lista
    |--------------------------------------------------------------------------
    |
    | Um por ação do financeiro. Cada um conhece a forma exata daquela resposta — o WHMCS entrega
    | alguns itens aninhados (`invoices.invoice`), outros em lista plana (`domains`, `contacts`) e
    | some com o contêiner quando a lista está vazia (`addons`) — e descarta, item por item, o que
    | não pertence à conta consultada. `password` e `pwresetkey` de contato, `username`/`password`
    | de serviço, anexo e `cc` de chamado não são lidos: credencial e cópia oculta não moram no CRM.
    |
    | Todo leitor abre conferindo o que a própria resposta diz da conta. Quando ela nomeia outro
    | número, o leitor devolve `null` — a tela escreve "não foi lida", nunca a lista de outra pessoa.
    */

    /** @return array<int, array<string, mixed>>|null */
    private function lerServicos(array $resposta, int $clientId, bool $filtrarVeiculacao): ?array
    {
        $confirmada = $this->contaConfirmada($resposta, $clientId);

        if ($confirmada === false) {
            return null;
        }

        return collect($this->lista($resposta['products']['product'] ?? $resposta['products'] ?? []))
            ->filter(fn (mixed $servico): bool => is_array($servico) && $this->pertenceAo($servico, $clientId, true))
            ->map(fn (array $servico): array => [
                'id' => (int) ($servico['id'] ?? 0),
                'nome' => (string) ($servico['name'] ?? $servico['productname'] ?? 'Serviço'),
                'grupo' => (string) ($servico['groupname'] ?? ''),
                'dominio' => (string) ($servico['domain'] ?? ''),
                'servidor' => (string) ($servico['servername'] ?? ''),
                'status' => (string) ($servico['status'] ?? ''),
                'proximo_vencimento' => $this->data($servico['nextduedate'] ?? null),
                'valor_cents' => $this->centavos($servico['recurringamount'] ?? $servico['totalrecurringamount'] ?? 0),
                'primeiro_pagamento_cents' => $this->centavos($servico['firstpaymentamount'] ?? 0),
                'ciclo' => (string) ($servico['billingcycle'] ?? ''),
                'contratado_em' => $this->data($servico['regdate'] ?? null),
                'atualizado_em' => $this->data($servico['lastupdate'] ?? null),
                'forma_de_pagamento' => $this->formaDePagamento($servico),
                'pedido_numero' => (string) (($servico['ordernumber'] ?? '') ?: ($servico['orderid'] ?? '')),
                'motivo_da_suspensao' => trim((string) ($servico['suspensionreason'] ?? '')),
                'observacoes' => trim((string) ($servico['notes'] ?? '')),
                'campos_extras' => $this->paresDeEtiqueta($servico['customfields'] ?? null),
                'opcoes' => $this->paresDeEtiqueta($servico['configoptions'] ?? null),
                'url' => $this->linkDaArea('productdetails', (int) ($servico['id'] ?? 0)),
            ])
            ->filter(fn (array $servico): bool => ! $filtrarVeiculacao
                || (Veiculacao::mostraServico($servico['status']) && Veiculacao::mostraGrupo($servico['grupo'])))
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>>|null */
    private function lerFaturas(array $resposta, int $clientId, bool $filtrarVeiculacao): ?array
    {
        $confirmada = $this->contaConfirmada($resposta, $clientId);

        if ($confirmada === false) {
            return null;
        }

        return collect($this->lista($resposta['invoices']['invoice'] ?? $resposta['invoices'] ?? []))
            ->filter(fn (mixed $fatura): bool => is_array($fatura) && $this->pertenceAo($fatura, $clientId, true))
            ->map(fn (array $fatura): array => [
                'id' => (int) ($fatura['id'] ?? 0),
                'numero' => (string) (($fatura['invoicenum'] ?? '') ?: ($fatura['id'] ?? '')),
                'emitida' => $this->data($fatura['date'] ?? null),
                'vencimento' => $this->data($fatura['duedate'] ?? null),
                'paga_em' => $this->data($fatura['datepaid'] ?? null),
                'cancelada_em' => $this->data($fatura['date_cancelled'] ?? null),
                'reembolsada_em' => $this->data($fatura['date_refunded'] ?? null),
                'ultima_tentativa' => $this->data($fatura['last_capture_attempt'] ?? null),
                'total_cents' => $this->valorDaFatura($fatura),
                'saldo_cents' => $this->saldoDaFatura($fatura),
                'subtotal_cents' => $this->centavos($fatura['subtotal'] ?? 0),
                'imposto_cents' => $this->centavos($fatura['tax'] ?? 0) + $this->centavos($fatura['tax2'] ?? 0),
                'credito_aplicado_cents' => $this->centavos($fatura['credit'] ?? 0),
                'status' => (string) ($fatura['status'] ?? ''),
                'metodo' => (string) ($fatura['paymentmethod'] ?? ''),
                'metodo_rotulo' => $this->formaDePagamento($fatura),
                'moeda' => $this->codigoDaMoeda($fatura['currencycode'] ?? $fatura['currency'] ?? null),
                'observacoes' => trim((string) ($fatura['notes'] ?? '')),
                'url' => $this->linkDaArea('viewinvoice', (int) ($fatura['id'] ?? 0), 'id'),
            ])
            ->filter(fn (array $fatura): bool => ! $filtrarVeiculacao || Veiculacao::mostraFatura($fatura['status']))
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>>|null */
    private function lerAddons(array $resposta, int $clientId): ?array
    {
        $confirmada = $this->contaConfirmada($resposta, $clientId);

        if ($confirmada === false) {
            return null;
        }

        return collect($this->lista($resposta['addons']['addon'] ?? $resposta['addons'] ?? []))
            ->filter(fn (mixed $addon): bool => is_array($addon) && $this->pertenceAo($addon, $clientId, true))
            ->map(fn (array $addon): array => [
                'id' => (int) ($addon['id'] ?? 0),
                'nome' => (string) ($addon['name'] ?? 'Adicional'),
                'servico' => trim((string) ($addon['productname'] ?? '')),
                'status' => (string) ($addon['status'] ?? ''),
                'valor_cents' => $this->centavos($addon['recurringamount'] ?? $addon['recurring'] ?? $addon['price'] ?? 0),
                'ciclo' => (string) ($addon['billingcycle'] ?? ''),
                'contratado_em' => $this->data($addon['regdate'] ?? null),
                'proximo_vencimento' => $this->data($addon['nextduedate'] ?? null),
                'forma_de_pagamento' => $this->formaDePagamento($addon),
                'url' => (int) ($addon['serviceid'] ?? 0) > 0
                    ? $this->linkDaArea('productdetails', (int) $addon['serviceid'])
                    : '',
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>>|null */
    private function lerDominios(array $resposta, int $clientId): ?array
    {
        $confirmada = $this->contaConfirmada($resposta, $clientId);

        if ($confirmada === false) {
            return null;
        }

        return collect($this->lista($resposta['domains']['domain'] ?? $resposta['domains'] ?? []))
            ->filter(fn (mixed $dominio): bool => is_array($dominio) && $this->pertenceAo($dominio, $clientId, true))
            ->map(fn (array $dominio): array => [
                'id' => (int) ($dominio['id'] ?? 0),
                'dominio' => (string) ($dominio['domain'] ?? ''),
                'tipo' => (string) ($dominio['type'] ?? ''),
                'registrador' => (string) ($dominio['registrar'] ?? ''),
                'status' => (string) ($dominio['status'] ?? ''),
                'registrado_em' => $this->data($dominio['registrationdate'] ?? null),
                'vence_em' => $this->data($dominio['expirydate'] ?? $dominio['due'] ?? null),
                'proximo_vencimento' => $this->data($dominio['nextduedate'] ?? null),
                'valor_cents' => $this->centavos($dominio['recurringamount'] ?? $dominio['firstpayment'] ?? 0),
                'ciclo' => (string) ($dominio['billingcycle'] ?? ($dominio['regperiod'] ?? '')),
                'renovacao_automatica' => $this->renovacaoAutomatica($dominio),
                'observacoes' => trim((string) ($dominio['notes'] ?? '')),
                'url' => $this->linkDaArea('domaindetails', (int) ($dominio['id'] ?? 0)),
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>>|null */
    private function lerTransacoes(array $resposta, int $clientId): ?array
    {
        $confirmada = $this->contaConfirmada($resposta, $clientId);

        if ($confirmada === false) {
            return null;
        }

        return collect($this->lista($resposta['transactions']['transaction'] ?? $resposta['transactions'] ?? []))
            ->filter(fn (mixed $transacao): bool => is_array($transacao) && $this->pertenceAo($transacao, $clientId, true))
            ->map(fn (array $transacao): array => [
                'id' => (int) ($transacao['id'] ?? 0),
                'data' => $this->data($transacao['date'] ?? null),
                'descricao' => trim((string) ($transacao['description'] ?? '')),
                'entrada_cents' => $this->centavos($transacao['amountin'] ?? 0),
                'saida_cents' => $this->centavos($transacao['amountout'] ?? 0),
                'taxa_cents' => $this->centavos($transacao['fees'] ?? 0),
                'forma' => $this->formaDePagamento($transacao),
                'moeda' => $this->codigoDaMoeda($transacao['currency'] ?? null),
                'referencia' => trim((string) ($transacao['transid'] ?? '')),
                'fatura_id' => (int) ($transacao['invoiceid'] ?? 0) > 0 ? (int) $transacao['invoiceid'] : null,
                'url' => (int) ($transacao['invoiceid'] ?? 0) > 0
                    ? $this->linkDaArea('viewinvoice', (int) $transacao['invoiceid'])
                    : '',
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>>|null */
    private function lerCreditos(array $resposta, int $clientId): ?array
    {
        $confirmada = $this->contaConfirmada($resposta, $clientId);

        if ($confirmada === false) {
            return null;
        }

        // O extrato de crédito não escreve dono em item nenhum, nem na conta #10045: a consulta é
        // feita pelo número da conta da ficha, e a resposta que nomear outra conta já foi barrada
        // acima. É a única leitura em que todos os itens chegam sem dono.
        return collect($this->lista($resposta['credits']['credit'] ?? $resposta['credits'] ?? []))
            ->filter(fn (mixed $credito): bool => is_array($credito) && $this->pertenceAo($credito, $clientId, true))
            ->map(fn (array $credito): array => [
                'id' => (int) ($credito['id'] ?? 0),
                'data' => $this->data($credito['date'] ?? null),
                'descricao' => trim((string) ($credito['description'] ?? '')),
                'valor_cents' => $this->centavos($credito['amount'] ?? 0),
                'fatura_id' => (int) ($credito['relid'] ?? 0) > 0 ? (int) $credito['relid'] : null,
                'url' => (int) ($credito['relid'] ?? 0) > 0
                    ? $this->linkDaArea('viewinvoice', (int) $credito['relid'])
                    : '',
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>>|null */
    private function lerPedidos(array $resposta, int $userId): ?array
    {
        $confirmada = $this->contaConfirmada($resposta, $userId);

        if ($confirmada === false) {
            return null;
        }

        return collect($this->lista($resposta['orders']['order'] ?? $resposta['orders'] ?? []))
            ->filter(fn (mixed $pedido): bool => is_array($pedido) && $this->pertenceAo($pedido, $userId, false))
            ->map(fn (array $pedido): array => [
                'id' => (int) ($pedido['id'] ?? 0),
                'numero' => (string) (($pedido['ordernum'] ?? '') ?: ($pedido['id'] ?? '')),
                'data' => $this->data($pedido['date'] ?? null),
                'status' => (string) ($pedido['status'] ?? ''),
                'pagamento' => (string) ($pedido['paymentstatus'] ?? ''),
                'valor_cents' => $this->centavos($pedido['amount'] ?? 0),
                'forma' => $this->formaDePagamento($pedido),
                'fatura_id' => (int) ($pedido['invoiceid'] ?? 0) > 0 ? (int) $pedido['invoiceid'] : null,
                'url' => (int) ($pedido['invoiceid'] ?? 0) > 0
                    ? $this->linkDaArea('viewinvoice', (int) $pedido['invoiceid'])
                    : '',
                'observacoes' => trim((string) ($pedido['notes'] ?? '')),
                'itens' => $this->linhasDeItem($pedido['lineitems'] ?? null),
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>>|null */
    private function lerContatos(array $resposta, int $userId): ?array
    {
        $confirmada = $this->contaConfirmada($resposta, $userId);

        if ($confirmada === false) {
            return null;
        }

        return collect($this->lista($resposta['contacts']['contact'] ?? $resposta['contacts'] ?? []))
            ->filter(fn (mixed $contato): bool => is_array($contato) && $this->pertenceAo($contato, $userId, false))
            ->map(fn (array $contato): array => [
                'id' => (int) ($contato['id'] ?? 0),
                'nome' => trim((string) (($contato['name'] ?? '')
                    ?: trim(($contato['firstname'] ?? '').' '.($contato['lastname'] ?? '')))),
                'empresa' => trim((string) ($contato['companyname'] ?? '')),
                'email' => mb_strtolower(trim((string) ($contato['email'] ?? ''))),
                'telefone' => $this->telefone($contato['phonenumber'] ?? ''),
                'documento' => trim((string) ($contato['tax_id'] ?? '')),
                'endereco' => implode(' - ', array_filter([
                    trim((string) ($contato['address1'] ?? '')),
                    trim((string) ($contato['address2'] ?? '')),
                ])),
                'cidade' => trim((string) ($contato['city'] ?? '')),
                'uf' => mb_strtoupper(trim((string) ($contato['state'] ?? ''))),
                'cep' => trim((string) ($contato['postcode'] ?? '')),
                'pais' => mb_strtoupper(trim((string) ($contato['country'] ?? ''))),
                'sub_conta' => $this->sinal($contato['subaccount'] ?? null),
                'permissoes' => $this->permissoesDoContato($contato['permissions'] ?? null),
                'avisos_por_email' => $this->avisosDoContato($contato),
                'desde' => $this->data($contato['created_at'] ?? null),
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>>|null */
    private function lerChamados(array $resposta, int $clientId): ?array
    {
        $confirmada = $this->contaConfirmada($resposta, $clientId);

        if ($confirmada === false) {
            return null;
        }

        return collect($this->lista($resposta['tickets']['ticket'] ?? $resposta['tickets'] ?? []))
            ->filter(fn (mixed $chamado): bool => is_array($chamado) && $this->pertenceAo($chamado, $clientId, true))
            ->map(fn (array $chamado): array => [
                'id' => (int) ($chamado['id'] ?? 0),
                'numero' => (string) (($chamado['ticketid'] ?? '') ?: ($chamado['tid'] ?? '')),
                'assunto' => trim((string) ($chamado['subject'] ?? '')),
                'departamento' => (string) ($chamado['deptname'] ?? ''),
                'status' => (string) ($chamado['status'] ?? ''),
                'prioridade' => (string) ($chamado['priority'] ?? ''),
                'aberto_em' => $this->data($chamado['date'] ?? null),
                'ultima_resposta' => $this->data($chamado['lastreply'] ?? null),
                'atendente' => trim((string) ($chamado['admin'] ?? '')),
                'servico' => trim((string) ($chamado['service'] ?? '')),
                'quem_abriu' => $this->solicitanteDoChamado($chamado),
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>>|null */
    private function lerOrcamentos(array $resposta, int $clientId): ?array
    {
        $confirmada = $this->contaConfirmada($resposta, $clientId);

        if ($confirmada === false) {
            return null;
        }

        // `GetQuotes` ignora o recado de conta e devolve a base inteira, conferido na produção.
        // O filtro por item é o único freio, e o item que não declara dono fica de fora.
        return collect($this->lista($resposta['quotes']['quote'] ?? $resposta['quotes'] ?? []))
            ->filter(fn (mixed $orcamento): bool => is_array($orcamento) && $this->pertenceAo($orcamento, $clientId, false))
            ->map(fn (array $orcamento): array => [
                'id' => (int) ($orcamento['id'] ?? 0),
                'assunto' => trim((string) ($orcamento['subject'] ?? '')),
                'status' => (string) (($orcamento['status'] ?? '') ?: ($orcamento['stage'] ?? '')),
                'criado_em' => $this->data($orcamento['datecreated'] ?? null),
                'enviado_em' => $this->data($orcamento['datesent'] ?? null),
                'aceito_em' => $this->data($orcamento['dateaccepted'] ?? null),
                'valido_ate' => $this->data($orcamento['validuntil'] ?? null),
                'total_cents' => $this->centavos($orcamento['total'] ?? 0) ?: ($this->centavos($orcamento['subtotal'] ?? 0)
                    + $this->centavos($orcamento['tax1'] ?? 0)
                    + $this->centavos($orcamento['tax2'] ?? 0)),
                'observacoes_para_o_cliente' => trim((string) ($orcamento['customernotes'] ?? '')),
                'itens' => $this->linhasDeItem($orcamento['items'] ?? null),
            ])
            ->values()
            ->all();
    }

    /**
     * As linhas de um pedido ou de um orçamento. Os dois vêm com nomes diferentes para a mesma
     * coisa (`product`/`desc`, `qty`/`quantity`, `price`/`unit_price`), e o que não existe volta
     * `null` em vez de zero — a tela omite a coluna, não inventa valor.
     *
     * @return array<int, array<string, mixed>>
     */
    private function linhasDeItem(mixed $bruto): array
    {
        return collect($this->lista(is_array($bruto) ? ($bruto['lineitem'] ?? $bruto) : []))
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'tipo' => (string) ($item['producttype'] ?? $item['type'] ?? ''),
                'nome' => trim((string) (($item['product'] ?? '')
                    ?: ($item['desc'] ?? ($item['description'] ?? ($item['title'] ?? ($item['domain'] ?? '')))))),
                'dominio' => trim((string) ($item['domain'] ?? '')),
                'ciclo' => (string) ($item['billingcycle'] ?? ''),
                'quantidade' => $this->quantidadeDoItem($item),
                'valor_unitario_cents' => $this->valorUnitarioDoItem($item),
                'valor_cents' => $this->centavos($item['amount'] ?? $item['subtotal'] ?? 0),
                'status' => (string) ($item['status'] ?? ''),
                'objeto_id' => (int) ($item['relid'] ?? 0) > 0 ? (int) $item['relid'] : null,
            ])
            ->filter(fn (array $item): bool => $item['nome'] !== '' || $item['dominio'] !== '' || $item['valor_cents'] !== 0)
            ->values()
            ->all();
    }

    private function quantidadeDoItem(array $item): ?int
    {
        foreach (['qty', 'quantity'] as $chave) {
            if (array_key_exists($chave, $item) && is_numeric($item[$chave])) {
                return (int) $item[$chave];
            }
        }

        return null;
    }

    private function valorUnitarioDoItem(array $item): ?int
    {
        foreach (['price', 'unit_price', 'unitprice'] as $chave) {
            if (array_key_exists($chave, $item)) {
                return $this->centavos($item[$chave]);
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | As leituras miúdas que as listas e o cadastro compartilham
    |--------------------------------------------------------------------------
    |
    | Cada método aqui existe porque o financeiro escreve a mesma ideia de mais de uma forma, e a
    | tela não pode escolher sozinho entre elas. A regra que atravessa todas: o que o financeiro não
    | disse volta ausência (`null` ou string vazia), nunca "não" nem zero — a ficha do cliente não
    | afirma que algo está desligado só porque a API não respondeu.
    */

    /**
     * Uma flag do financeiro lida pelo que ela é: ligada, desligada ou não dita. As instalações
     * escrevem `1`/`0`, `on`/`off`, `true`/`false` e até `Active`/`Inactive` para a mesma caixa, e o
     * campo some da resposta quando a versão não o conhece.
     */
    private function sinal(mixed $valor): ?bool
    {
        if ($valor === null) {
            return null;
        }

        if (is_bool($valor)) {
            return $valor;
        }

        $texto = trim((string) $valor);

        if ($texto === '') {
            return null;
        }

        if (is_numeric($texto)) {
            return (float) $texto !== 0.0;
        }

        return match (Str::lower($texto)) {
            'on', 'true', 'yes', 'sim', 'active', 'enabled', 'allow', 'allowed', 'opt-in', 'optin' => true,
            'off', 'false', 'no', 'nao', 'não', 'inactive', 'disabled', 'deny', 'denied', 'opt-out', 'optout' => false,
            default => null,
        };
    }

    /**
     * O IP do último acesso, que mora no mesmo texto da data (ver `ultimoAcesso()`). A equipe usa a
     * origem para conferir uma reclamação de acesso — sem ele a ficha diria "entrou ontem" e nada mais.
     */
    private function ipDoUltimoAcesso(mixed $valor): string
    {
        $texto = trim((string) $valor);

        return preg_match('/IP\s*Address:\s*([0-9a-fA-F:.]{4,64})/', $texto, $achado) === 1
            ? trim($achado[1])
            : '';
    }

    /**
     * O meio de pagamento em português. O pedido e a fatura trazem o nome de exibição
     * (`paymentmethodname`); a transação e o cadastro trazem só o código técnico, e código não é
     * texto para a tela. Meio que o mapa não conhece aparece como o financeiro o escreveu.
     */
    private function formaDePagamento(array $item): string
    {
        $rotulo = trim((string) ($item['paymentmethodname'] ?? ''));

        if ($rotulo !== '') {
            return $rotulo;
        }

        // A transação chama o meio de pagamento de `gateway`; serviço, fatura e pedido usam
        // `paymentmethod`. As duas grafias levam ao mesmo rótulo da config.
        $codigo = mb_strtolower(trim((string) ($item['paymentmethod'] ?? ''))
            ?: trim((string) ($item['gateway'] ?? '')));

        if ($codigo === '') {
            return '';
        }

        return (string) (config('whmcs.formas_pagamento')[$codigo] ?? $codigo);
    }

    /**
     * O código da moeda por trás do número que a API devolve. `GetCurrencies` não existe nesta
     * instalação (403), então a única tradução provável é a moeda da própria conta, guardada quando
     * o cadastro foi lido. Sem par conhecido, volta vazio e a tela omite a moeda.
     */
    private function codigoDaMoeda(mixed $bruto): string
    {
        $texto = trim((string) $bruto);

        if ($texto === '') {
            return '';
        }

        return is_numeric($texto)
            ? (string) ($this->moedas[(int) $texto] ?? '')
            : mb_strtoupper($texto);
    }

    /**
     * O total da fatura. Algumas versões escrevem `total` e outras só as partes; somar as partes
     * quando o total falta é a mesma conta que o financeiro faz na tela dele.
     */
    private function valorDaFatura(array $fatura): int
    {
        $total = $this->centavos($fatura['total'] ?? 0);

        if ($total !== 0) {
            return $total;
        }

        return $this->centavos($fatura['subtotal'] ?? 0)
            + $this->centavos($fatura['tax'] ?? 0)
            + $this->centavos($fatura['tax2'] ?? 0);
    }

    /**
     * O que ainda falta pagar. `balance` é o recado direto do financeiro; sem ele a conta é a
     * dele também — total menos o que já entrou, menos crédito aplicado. Errar aqui faria a ficha
     * cobrar de novo quem já pagou, então fatura paga, reembolsada ou cancelada tem saldo zero.
     */
    private function saldoDaFatura(array $fatura): int
    {
        if (array_key_exists('balance', $fatura)) {
            return $this->centavos($fatura['balance']);
        }

        $status = Str::lower(trim((string) ($fatura['status'] ?? '')));

        if (in_array($status, ['paid', 'refunded', 'cancelled', 'paga', 'reembolsada', 'cancelada'], true)) {
            return 0;
        }

        return max(0, $this->valorDaFatura($fatura)
            - $this->centavos($fatura['payments'] ?? 0)
            - $this->centavos($fatura['credit'] ?? 0));
    }

    /**
     * Pares rótulo/valor no formato que o WHMCS desenha — campos personalizados do serviço, opções
     * de configuração. O nome do rótulo muda de uma resposta para outra (`label`, `friendlyName`,
     * `name`), o valor quase nunca. Item sem rótulo ou sem valor fica de fora: a tela não mostra
     * campo que o financeiro não preencheu.
     *
     * @return array<int, array{rotulo: string, valor: string}>
     */
    private function paresDeEtiqueta(mixed $bruto): array
    {
        if (! is_array($bruto)) {
            return [];
        }

        $itens = $this->lista($bruto['customfield'] ?? $bruto['configoption'] ?? $bruto);

        return collect($itens)
            ->filter(fn (mixed $par): bool => is_array($par))
            ->map(fn (array $par): array => [
                'rotulo' => trim((string) ($par['label']
                    ?? $par['friendlyName']
                    ?? $par['name']
                    ?? $par['varname']
                    ?? '')),
                'valor' => trim((string) ($par['value'] ?? $par['optionValue'] ?? '')),
            ])
            ->filter(fn (array $par): bool => $par['rotulo'] !== '' && $par['valor'] !== '')
            ->values()
            ->all();
    }

    /**
     * As preferências de e-mail da conta. Há instalações que devolvem a lista do que está ligado
     * (nomes soltos) e outras que devolvem par por par com `on`/`off`; as duas dizem a mesma coisa.
     * Nome que o mapa não conhece aparece como veio, porque sumir com uma preferência marcada é
     * pior do que mostrá-la com o nome de lá.
     *
     * @return array<int, array{rotulo: string, ativo: bool}>
     */
    private function preferenciasDeEmail(mixed $bruto): array
    {
        if (! is_array($bruto)) {
            return [];
        }

        $mapa = (array) config('whmcs.preferencias_email');

        return collect($this->lista($bruto['preference'] ?? $bruto))
            ->map(function (mixed $item) use ($mapa): ?array {
                if (is_string($item)) {
                    $chave = trim($item);

                    return $chave === '' ? null : ['rotulo' => (string) ($mapa[$chave] ?? $chave), 'ativo' => true];
                }

                if (! is_array($item)) {
                    return null;
                }

                $chave = trim((string) ($item['key'] ?? $item['name'] ?? $item['varname'] ?? ''));
                $rotulo = trim((string) ($item['label'] ?? $item['friendlyName'] ?? ''))
                    ?: (string) ($mapa[$chave] ?? $chave);

                $ativo = $this->sinal($item['value'] ?? $item['enabled'] ?? null);

                return $rotulo === '' || $ativo === null ? null : ['rotulo' => $rotulo, 'ativo' => $ativo];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * O cartão que o financeiro mantém em reserva para a cobrança automática. Só bandeira, os
     * últimos dígitos e a validade: o número completo, o CVV e o token não são lidos — a equipe
     * confere qual cartão está guardado sem que a ficha do CRM vira um arquivo de cartão.
     *
     * @return array{bandeira: string, ultimos_digitos: string, validade: string}
     */
    private function cartaoDeReserva(array $cliente): array
    {
        $bandeira = trim((string) ($cliente['cardtype'] ?? ''));
        $ultimos = trim((string) (($cliente['lastfour'] ?? '') ?: ($cliente['cardlastfour'] ?? '')));

        $mes = trim((string) ($cliente['expmonth'] ?? ''));
        $ano = trim((string) ($cliente['expyear'] ?? ''));
        $validade = $mes !== '' && $ano !== '' ? $mes.'/'.$ano : ($mes ?: $ano);

        if ($bandeira === '' && $ultimos === '' && $validade === '') {
            return [];
        }

        return ['bandeira' => $bandeira, 'ultimos_digitos' => $ultimos, 'validade' => $validade];
    }

    /**
     * Os acessos que a conta tem — o titular e as subcontas. `password`, `token` e chave de
     * redefinição não entram: credencial não mora no CRM nem na tela da equipe.
     *
     * @return array<int, array<string, mixed>>
     */
    private function usuariosDaConta(mixed $bruto): array
    {
        if (! is_array($bruto)) {
            return [];
        }

        return collect($this->lista($bruto['user'] ?? $bruto))
            ->filter(fn (mixed $usuario): bool => is_array($usuario))
            ->map(fn (array $usuario): array => [
                'id' => (int) ($usuario['id'] ?? 0),
                'nome' => trim((string) (($usuario['name'] ?? '')
                    ?: ($usuario['fullname'] ?? trim(($usuario['firstname'] ?? '').' '.($usuario['lastname'] ?? ''))))),
                'email' => mb_strtolower(trim((string) ($usuario['email'] ?? ''))),
                'telefone' => $this->telefone($usuario['phonenumber'] ?? ''),
                'titular' => $this->sinal($usuario['owner'] ?? null),
                'verificado' => $this->sinal($usuario['verified'] ?? $usuario['email_verified'] ?? null),
                'desde' => $this->data($usuario['created_at'] ?? $usuario['datecreated'] ?? null),
                'ultimo_acesso' => $this->ultimoAcesso($usuario['lastlogin'] ?? null),
            ])
            ->filter(fn (array $usuario): bool => $usuario['nome'] !== '' || $usuario['email'] !== '')
            ->values()
            ->all();
    }

    /**
     * O resumo que o financeiro já calcula para a própria ficha (`stats` de `GetClientsDetails`).
     * A soma daqui é a do financeiro — refazer a conta em PHP faria a ficha discordar da cobrança.
     * Chave que não veio fica de fora em vez de virar zero.
     *
     * @return array<string, mixed>
     */
    private function resumoDoFinanceiro(mixed $bruto): array
    {
        if (! is_array($bruto)) {
            return [];
        }

        $contagens = [
            'faturas_em_aberto' => 'inunpaid',
            'servicos' => 'nproducts',
            'servicos_ativos' => 'activeservices',
            'dominios' => 'domains',
            'dominios_em_analise' => 'evaluateddomains',
            'dominios_cancelados' => 'cancelleddomains',
        ];

        $resumo = [];

        foreach ($contagens as $nome => $chave) {
            if (array_key_exists($chave, $bruto) && is_numeric($bruto[$chave])) {
                $resumo[$nome] = (int) $bruto[$chave];
            }
        }

        if (array_key_exists('inunpaidbalance', $bruto)) {
            $resumo['em_aberto_cents'] = $this->centavos($bruto['inunpaidbalance']);
        }

        $proximo = $this->data($bruto['nextduedate'] ?? null);

        if ($proximo !== null) {
            $resumo['proximo_vencimento'] = $proximo;
        }

        return $resumo;
    }

    /**
     * A renovação automática do domínio. O financeiro escreve isto como campo próprio ou como o
     * inverso (`donotrenew`), conforme a versão; os dois caminhos apontam para a mesma decisão da
     * pessoa. Ausente volta `null` — a tela diz "não informado", nunca "renovação desligada", porque
     * desligar a renovação de um cliente a partir de uma chave que não veio seria um erro caro.
     */
    private function renovacaoAutomatica(array $dominio): ?bool
    {
        foreach (['auto_renew', 'autorenew', 'mark_as_paid_renew'] as $chave) {
            if (array_key_exists($chave, $dominio)) {
                return $this->sinal($dominio[$chave]);
            }
        }

        foreach (['donotrenew', 'donottransfer'] as $inverso) {
            if (array_key_exists($inverso, $dominio)) {
                $flag = $this->sinal($dominio[$inverso]);

                return $flag === null ? null : ! $flag;
            }
        }

        return null;
    }

    /**
     * As caixas de e-mail que um contato recebe, já em português. O WHMCS guarda uma lista separada
     * por vírgula de chaves técnicas; permissão que o mapa não conhece aparece como veio, porque
     * sumir com uma caixa marcada é pior do que mostrá-la com o nome de lá.
     *
     * @return array<int, string>
     */
    private function permissoesDoContato(mixed $bruto): array
    {
        if ($bruto === null || $bruto === '') {
            return [];
        }

        $chaves = is_array($bruto)
            ? collect($this->lista($bruto))
                ->map(fn (mixed $permissao): string => trim((string) (is_array($permissao)
                    ? ($permissao['permission'] ?? $permissao['name'] ?? '')
                    : $permissao)))
                ->filter()
                ->all()
            : array_filter(array_map('trim', explode(',', (string) $bruto)));

        $mapa = (array) config('whmcs.permissoes_contato');

        return array_values(array_unique(array_map(
            fn (string $chave): string => (string) ($mapa[$chave] ?? $chave),
            $chaves,
        )));
    }

    /**
     * Os avisos que chegam ao contato por e-mail. São três caixas na tela do financeiro, e elas só
     * existem para subconta: o contato que é apenas contato de cobrança não declara nenhuma, e a
     * ficha não escreve "não recebe" em cima de ausência.
     *
     * @return array<int, array{rotulo: string, ativo: bool}>
     */
    private function avisosDoContato(array $contato): array
    {
        $caixas = [
            'domain_emails' => 'Domínios',
            'invoice_emails' => 'Faturas',
            'ticket_emails' => 'Chamados',
        ];

        $avisos = [];

        foreach ($caixas as $campo => $rotulo) {
            $ativo = $this->sinal($contato[$campo] ?? null);

            if ($ativo !== null) {
                $avisos[] = ['rotulo' => $rotulo, 'ativo' => $ativo];
            }
        }

        return $avisos;
    }

    /**
     * Quem abriu o chamado. O financeiro distingue o titular da conta do contato que fala por ela,
     * e a equipe precisa dessa diferença ao atender: quem atende o telefone pode ser o preposto.
     */
    private function solicitanteDoChamado(array $chamado): string
    {
        return trim((string) ($chamado['contactname'] ?? $chamado['name'] ?? ''));
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
     * A situação de um domínio. Além dos estados do serviço, o registro tem os seus (graça,
     * redenção, expiração) e é deles que a equipe fala quando o cliente pergunta da renovação.
     */
    public static function rotuloDoDomino(string $status): string
    {
        return (string) (config('whmcs.dominios_status')[$status]['rotulo'] ?? ($status !== '' ? $status : 'Sem situação'));
    }

    public static function tomDoDomino(string $status): string
    {
        return (string) (config('whmcs.dominios_status')[$status]['tom'] ?? 'neutral');
    }

    /** Como o domínio chegou à conta: registrado aqui, transferido, ou domínio raiz novo. */
    public static function rotuloDoTipoDeDominio(string $tipo): string
    {
        return (string) (config('whmcs.dominios_tipos')[$tipo] ?? $tipo);
    }

    /** A situação de um pedido do financeiro. */
    public static function rotuloDoPedido(string $status): string
    {
        return (string) (config('whmcs.pedidos_status')[$status]['rotulo'] ?? ($status !== '' ? $status : 'Sem situação'));
    }

    public static function tomDoPedido(string $status): string
    {
        return (string) (config('whmcs.pedidos_status')[$status]['tom'] ?? 'neutral');
    }

    /** A situação de um chamado do financeiro — separada da do chamado do portal. */
    public static function rotuloDoChamado(string $status): string
    {
        return (string) (config('whmcs.chamados_status')[$status]['rotulo'] ?? ($status !== '' ? $status : 'Sem situação'));
    }

    public static function tomDoChamado(string $status): string
    {
        return (string) (config('whmcs.chamados_status')[$status]['tom'] ?? 'neutral');
    }

    public static function rotuloDaPrioridade(string $prioridade): string
    {
        return (string) (config('whmcs.chamados_prioridade')[$prioridade]['rotulo'] ?? $prioridade);
    }

    public static function tomDaPrioridade(string $prioridade): string
    {
        return (string) (config('whmcs.chamados_prioridade')[$prioridade]['tom'] ?? 'neutral');
    }

    /** A situação de um orçamento do financeiro. */
    public static function rotuloDoOrcamento(string $status): string
    {
        return (string) (config('whmcs.orcamentos_status')[$status]['rotulo'] ?? ($status !== '' ? $status : 'Sem situação'));
    }

    public static function tomDoOrcamento(string $status): string
    {
        return (string) (config('whmcs.orcamentos_status')[$status]['tom'] ?? 'neutral');
    }

    /** O que cada linha de um pedido é — "Hosting" é o nome do WHMCS para qualquer produto. */
    public static function rotuloDoTipoDeItem(string $tipo): string
    {
        return (string) (config('whmcs.itens_pedido')[$tipo] ?? $tipo);
    }

    /** O departamento do chamado no nome que a equipe daqui usa. */
    public static function rotuloDoDepartamento(string $departamento): string
    {
        return (string) (config('whmcs.departamentos')[$departamento] ?? $departamento);
    }

    /**
     * O sim/não da tela para uma flag do financeiro. `null` é "o financeiro não disse", que não é
     * "não" — a ficha diz "não informado" em vez de afirmar o que a API não respondeu.
     */
    public static function rotuloDoSinal(?bool $sinal): string
    {
        return match ($sinal) {
            true => 'Sim',
            false => 'Não',
            null => 'Não informado',
        };
    }

    /** O tom do selo de uma flag: verde liga, cinza desliga, âmbar ausência. */
    public static function tomDoSinal(?bool $sinal): string
    {
        return match ($sinal) {
            true => 'green',
            false => 'neutral',
            null => 'amber',
        };
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

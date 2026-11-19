<?php

namespace App\Services\Assistente;

use App\Mail\SegundaViaFatura as EmailDaSegundaVia;
use App\Models\Activity;
use App\Models\Assistente;
use App\Models\Conversa;
use App\Models\ConversaMensagem;
use App\Models\Customer;
use App\Services\Assistente\Contratos\Acao;
use App\Services\Fatura\FaturaPdfService;
use App\Services\Whmcs\Cliente as Financeiro;
use App\Services\Whmcs\Veiculacao;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * A segunda via da fatura saindo pelo chat — a primeira ação do assistente, e a única que erra caro.
 *
 * O resto do atendimento pode errar uma frase; isto entrega dado financeiro de uma pessoa para
 * outra. Por isso a ação não pergunta nada a uma IA e não envia nada a um endereço que alguém
 * digitou: ela localiza a ficha pelo e-mail informado, exige a conferência dos dados que já estão
 * gravados nela (`Verificacao`) e manda para o endereço cadastrado, que é a coluna do banco, nunca
 * o texto da conversa.
 *
 * A conversa atravessa dois passos, gravados em `conversas.acao_passo`, e nada mais:
 *
 * `email` — quem é a pessoa. Não achou a conta, diz que não achou e deixa tentar de novo; uma
 * máquina que finge ter achado é pior que uma que erra uma vez a mais.
 *
 * `conferir` — um dado por vez, na ordem que o painel escolheu, até `assistente.verificacao.minimo`
 * bater. Errado, a resposta é sempre a mesma ("não bateu com o cadastro") e nunca qual campo nem
 * qual era o certo: conferência que corrige é consulta que vaza. Passada a régua, e só se o painel
 * permitir (`assistente.verificacao.completar`), a mesma etapa coleta a data de nascimento de quem
 * não tem nenhuma — a única escrita da conferência, e nunca sobre campo já preenchido. A pergunta
 * não segura o envio: recusa ou formato incompreendido, a fatura sai e a data continua em branco.
 *
 * Três caminhos e nenhum envio indevido no fim: passou da conta, envia e amarra a conversa à ficha;
 * estourou as tentativas, ou a ficha não tem o que conferir, ou o financeiro está fora do ar, a
 * conversa vai inteira para a equipe com o motivo. Desistir nunca é virar a tela para o cliente.
 *
 * O `minimo` cede quando a ficha tem menos campos conferíveis do que ele pede: conferir tudo o que
 * existe é o teto do que aquela conta pode provar, e a régua continua rígida onde o dado existe.
 */
final class SegundaViaDaFatura implements Acao
{
    public const CODIGO = 'segunda_via';

    /** Os dois passos da conversa com o financeiro. */
    private const EMAIL = 'email';

    private const CONFERIR = 'conferir';

    public const CANAL_CHAT = 'chat';

    public const CANAL_EMAIL = 'email';

    public const CANAL_AMBOS = 'ambos';

    /**
     * O que é pedido de segunda via.
     *
     * Lidas no texto já sem acento e em minúsculas, porque quem escreve no celular não acentua e
     * não espera que a máquina saiba ler "2ª via". A lista é intencionalmente larga no convite
     * ("manda a fatura", "boleto atualizado", "pdf da fatura", "fatura no chat") e estreita o suficiente para não abrir uma
     * conferência de segurança porque o visitante falou em "conta" numa dúvida sobre a plataforma.
     */
    private const GATILHOS = [
        '/\b(?:segund[ao]\s+|2\s*[ao]?\s+)vi[ao]\b/',
        '/\bvi[ao]\s+d[ae]\s+(?:[a-z]+\s+){0,2}(?:fatura|boleto|conta|cobranca|mensalidade)\b/',
        '/\b(?:reenvi|envi|mand|pass|emit|tir|baix|peg|ger)[a-z]*\s+(?:me\s+)?(?:a|o|minha|meu|pro|pra|aqui)?\s*(?:[a-z]+\s+){0,2}?(?:fatura|boleto|conta|cobranca)\b/',
        '/\b(?:fatura|boleto)\b.{0,35}\b(?:email|e\s*mail|mel|chat|aqui|pdf)\b/',
        '/\b(?:pdf\s+d[ae]\s+|quero\s+(?:a|minha)?\s*|preciso\s+d[ae]\s+)(?:fatura|boleto)\b/',
    ];

    public function codigo(): string
    {
        return self::CODIGO;
    }

    public function rotulo(): string
    {
        return 'Segunda via da fatura';
    }

    /**
     * O nome em português dos dois passos. A equipe precisa saber se a conversa está presa no
     * e-mail ou na conferência para saber de onde retomá-la — e só esta classe conhece os códigos.
     */
    public function rotuloDoPasso(?string $passo): string
    {
        return match ($passo) {
            self::EMAIL => 'esperando o e-mail do cadastro',
            self::CONFERIR => 'conferindo os dados da ficha',
            default => 'sem passo em curso',
        };
    }

    /**
     * O interruptor é o mesmo da tela de veiculação do financeiro: desligado `faturas`, desligada a
     * segunda via — o chat não pode abrir uma porta que o painel acabou de fechar.
     */
    public function ligada(): bool
    {
        return Veiculacao::segundaVia()
            && Assistente::permissaoAtiva('consultar_faturas');
    }

    public function gatilho(string $fala): bool
    {
        $texto = self::semAcento($fala);

        foreach (self::GATILHOS as $padrao) {
            if (preg_match($padrao, $texto) === 1) {
                return true;
            }
        }

        return false;
    }

    public function abrir(Conversa $conversa): string
    {
        $canal = $this->canalPreferidoNaConversa($conversa);
        $conversa->abrirAcao(self::CODIGO, self::EMAIL, $canal !== null ? ['canal_entrega' => $canal] : []);

        // O e-mail que a pessoa já deu no chat não é pedido de novo: a porta do atendimento
        // personalizado (identificação exigida) garante que ele existe quando a ação abre, e refazer
        // a primeira pergunta é o jeito mais rápido de o cliente achar que ninguém leu nada.
        $ficha = self::fichaDoEmailDito($conversa);

        if ($ficha !== null) {
            $abertura = match ($canal) {
                self::CANAL_CHAT => 'Encontrei a conta do e-mail que você já me deu. Antes de enviar a fatura aqui no chat, preciso conferir um dado seu: ',
                self::CANAL_AMBOS => 'Encontrei a conta do e-mail que você já me deu. Antes de enviar no chat e no e-mail de cadastro, preciso conferir um dado seu: ',
                default => 'Encontrei a conta do e-mail que você já me deu. Antes de enviar (no chat em PDF ou no seu e-mail de cadastro — é só me dizer onde prefere), preciso conferir um dado seu: ',
            };

            return $this->pelaFicha($conversa, $ficha, $abertura);
        }

        return $this->chame($conversa, 'Certo, eu cuido disso para você. Primeiro me diga o e-mail do seu cadastro — '
            .'é por ele que eu localizo a sua conta. Se quiser, já me diga também se prefere receber a fatura aqui no chat ou no e-mail.');
    }

    public function atender(Conversa $conversa, string $fala): string
    {
        $canalNaFala = self::detectarCanal($fala);

        if ($conversa->acao_em === null || $conversa->acao_em->lessThan(now()->subMinutes(Verificacao::minutos()))) {
            $canal = $canalNaFala ?? $this->canalPreferidoNaConversa($conversa);
            $conversa->abrirAcao(self::CODIGO, self::EMAIL, $canal !== null ? ['canal_entrega' => $canal] : []);

            $ficha = self::fichaDoEmailDito($conversa);

            if ($ficha !== null) {
                return $this->pelaFicha($conversa, $ficha,
                    'A conversa ficou parada e, por segurança, aquela conferência não vale mais. '
                        .'Vale de novo, a partir de agora: ');
            }

            return $this->chame($conversa, 'A conversa ficou parada e, por segurança, aquela conferência não vale '
                .'mais. Vamos de novo: me diga o e-mail do seu cadastro.');
        }

        return $conversa->acao_passo === self::EMAIL
            ? $this->peloEmail($conversa, $fala, $canalNaFala)
            : $this->pelaConferencia($conversa, $fala, $canalNaFala);
    }

    /**
     * O passo que localiza a ficha. Só depois dele é que existe "seu cadastro" para conferir — e o
     * e-mail informado não vira destino de nada: o destino é sempre o que está na coluna da ficha.
     */
    private function peloEmail(Conversa $conversa, string $fala, ?string $canalNaFala = null): string
    {
        $email = self::extrairEmail($fala, $canalNaFala !== null);

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->recusa($conversa, self::EMAIL,
                'Isso não parece um endereço de e-mail. Me manda ele inteiro, com o @ e o final, por favor.');
        }

        $ficha = Customer::porEmail($email);

        if ($ficha === null) {
            return $this->recusa($conversa, self::EMAIL,
                'Não localizei nenhuma conta com esse e-mail. Confere se é o mesmo que você usa para entrar no portal.');
        }

        if ($canalNaFala !== null) {
            $dados = (array) $conversa->acao_dados_json;
            $dados['canal_entrega'] = $canalNaFala;
            $conversa->forceFill(['acao_dados_json' => $dados])->save();
        }

        // O endereço que serviu fica na conversa: é o fim da pergunta, não o de uma conferência — a
        // ficha só é amarrada depois que os dados baterem.
        $conversa->update(['email' => $email]);

        return $this->pelaFicha($conversa, $ficha, 'Conta encontrada. ');
    }

    /**
     * O começo da conferência de uma ficha já achada — pelo e-mail dito na hora ou pelo que a pessoa
     * informou no atendimento. É um caminho só porque a régua (`Verificacao::exigiveis`) não pode
     * depender de como a ficha foi encontrada.
     */
    private function pelaFicha(Conversa $conversa, Customer $ficha, string $abertura): string
    {
        $conferiveis = Verificacao::exigiveis($ficha);

        if ($conferiveis === []) {
            return $this->chameALequipe($conversa, 'verificacao_impossivel',
                'A ficha '.$ficha->id.' não tem nenhum dos dados que a casa escolheu conferir.',
                'Sua conta não tem, aqui no cadastro, os dados que eu preciso conferir por aqui — então eu não envio '
                    .'nada antes de falar com uma pessoa. Já chamei a equipe: em instantes ela continua com você.');
        }

        $dados = [
            'ficha' => $ficha->id,
            'conferindo' => $conferiveis,
            'acertos' => 0,
            'necessarios' => min(Verificacao::minimo(), count($conferiveis)),
        ];

        $canal = ($conversa->acao_dados_json['canal_entrega'] ?? null) ?: $this->canalPreferidoNaConversa($conversa);

        if (is_string($canal) && $canal !== '') {
            $dados['canal_entrega'] = $canal;
        }

        $conversa->passoDaAcao(self::CONFERIR, $dados);

        return $this->chame($conversa, $abertura.Verificacao::pergunta($conferiveis[0]));
    }

    /**
     * O passo que confere, um dado por vez. A mesa de trabalho (`acao_dados_json`) guarda quais
     * campos faltam e quantos já bateram — nunca o que a pessoa respondeu.
     */
    private function pelaConferencia(Conversa $conversa, string $fala, ?string $canalNaFala = null): string
    {
        $dados = (array) $conversa->acao_dados_json;

        if ($canalNaFala !== null) {
            $dados['canal_entrega'] = $canalNaFala;
        }

        $campo = (string) ($dados['conferindo'][0] ?? '');
        $completando = (string) ($dados['completando'] ?? '');
        $ficha = ($campo === '' && $completando === '') ? null : Customer::find((int) ($dados['ficha'] ?? 0));

        if ($ficha === null) {
            $conversa->abrirAcao(self::CODIGO, self::EMAIL);

            return $this->chame($conversa, 'Preciso recomeçar: me diga de novo o e-mail que você usa no seu cadastro.');
        }

        // A prova já passou: o que resta é completar a ficha. É outro caminho porque outro é o
        // interesse — aqui uma resposta fora do formato não pode custar a fatura de quem pediu.
        if ($completando !== '') {
            return $this->pelaComplementacao($conversa, $ficha, $dados, $fala);
        }

        $respostaLimpa = $canalNaFala !== null ? self::limparPreferenciaDeCanal($fala) : $fala;
        $valorConferencia = $respostaLimpa !== '' ? $respostaLimpa : $fala;

        if (! Verificacao::confere($ficha, $campo, $valorConferencia)) {
            $tentativas = (int) $conversa->acao_tentativas + 1;

            if ($tentativas >= Verificacao::tentativas()) {
                return $this->chameALequipe($conversa, 'verificacao_sem_sucesso',
                    'A conferência da ficha '.$ficha->id.' não passou em '.$tentativas.' tentativas.',
                    'Não consegui confirmar os dados com o que está no cadastro, e assim eu não envio nada — '
                        .'seria entregar informação sua para quem não é você. Já chamei a equipe para continuar com você.');
            }

            $conversa->passoDaAcao(self::CONFERIR, $dados, $tentativas);

            return $this->chame($conversa, 'Não bateu com o cadastro. Pode conferir e me mandar de novo? '
                .Verificacao::pergunta($campo));
        }

        $faltam = array_slice($dados['conferindo'], 1);
        $dados['conferindo'] = $faltam;
        $dados['acertos'] = (int) $dados['acertos'] + 1;

        if ($faltam === [] || $dados['acertos'] >= (int) $dados['necessarios']) {
            return $this->depoisDaProva($conversa, $ficha, $dados);
        }

        $conversa->passoDaAcao(self::CONFERIR, $dados);

        return $this->chame($conversa, 'Isso mesmo. '.Verificacao::pergunta($faltam[0]));
    }

    /**
     * A régua passou e o envio já está liberado. Antes de mandar, a ficha de quem não tem data de
     * nascimento pode ficar completa — e só de quem não tem: campo preenchido não é perguntado nem
     * trocado, porque o cadastro da casa não se reescreve com texto de chat.
     */
    private function depoisDaProva(Conversa $conversa, Customer $ficha, array $dados): string
    {
        $canal = is_string($dados['canal_entrega'] ?? null) ? $dados['canal_entrega'] : null;

        if (! Verificacao::completaNascimento() || filled($ficha->nascimento)) {
            return $this->envie($conversa, $ficha, '', $canal);
        }

        $dados['conferindo'] = [];
        $dados['completando'] = 'nascimento';
        $dados['completando_tentativas'] = 0;
        $conversa->passoDaAcao(self::CONFERIR, $dados);

        return $this->chame($conversa, 'Identidade confirmada. Antes de eu mandar a fatura: a sua data de '
            .'nascimento está em branco no cadastro, e eu aproveito para completar. Me diga no formato '
            .'dia/mês/ano — se preferir não informar, responda "depois" que a via sai do mesmo jeito.');
    }

    /**
     * A resposta da data, já com a identidade provada. Vale uma tentativa de formato e mais nada:
     * a fatura estava liberada antes desta pergunta, e nenhuma coleta de dado pode segurá-la.
     */
    private function pelaComplementacao(Conversa $conversa, Customer $ficha, array $dados, string $fala): string
    {
        $canalNaFala = self::detectarCanal($fala);
        $canal = $canalNaFala ?? (is_string($dados['canal_entrega'] ?? null) ? $dados['canal_entrega'] : null);
        $respostaLimpa = $canalNaFala !== null ? self::limparPreferenciaDeCanal($fala) : $fala;
        $valorData = $respostaLimpa !== '' ? $respostaLimpa : $fala;
        $data = Verificacao::dataDaResposta($valorData);

        if ($data !== null) {
            $ficha->update(['nascimento' => $data]);

            Activity::log('cliente_nascimento_pelo_chat',
                'A data de nascimento foi informada pelo cliente no chat, depois de a identidade ser '
                    .'conferida, e gravada na ficha.', $ficha, [], 'Assistente virtual');

            return $this->envie($conversa, $ficha, 'Anotei sua data de nascimento no cadastro. ', $canal);
        }

        $tentativas = (int) ($dados['completando_tentativas'] ?? 0) + 1;

        if ($tentativas >= 2 || self::recusouAData($valorData)) {
            return $this->envie($conversa, $ficha, 'Tudo bem, deixei a data de nascimento em branco. ', $canal);
        }

        $dados['completando_tentativas'] = $tentativas;
        if ($canal !== null) {
            $dados['canal_entrega'] = $canal;
        }
        $conversa->passoDaAcao(self::CONFERIR, $dados);

        return $this->chame($conversa, 'Não entendi como data. Me manda no formato dia/mês/ano (por exemplo, '
            .'27/10/1990) ou responda "depois" que eu envio a fatura sem ela.');
    }

    /** "depois", "não quero", "prefiro não": recusar-se a completar a ficha é uma resposta válida. */
    private static function recusouAData(string $fala): bool
    {
        return in_array(self::semAcento($fala), [
            'depois', 'deixa', 'deixa pra depois', 'nao', 'nao sei', 'nao quero',
            'prefiro nao', 'agora nao', 'pular', 'sem data',
        ], true);
    }

    /**
     * O envio, e a única saída que amarra a conversa à ficha. Ele é sincrono de propósito: a máquina
     * só pode dizer "enviei" depois que o servidor respondeu, e uma fila que ninguém drenou faria o
     * cliente esperar por um e-mail que nunca saiu.
     *
     * `$abertura` é a frase que fecha o que veio antes do envio — hoje, o registro da data de
     * nascimento — e acompanha todas as saídas para a conversa não mudar de assunto no meio.
     */
    private function envie(Conversa $conversa, Customer $ficha, string $abertura = '', ?string $canalExplicito = null): string
    {
        $canal = $canalExplicito
            ?: (string) ($conversa->acao_dados_json['canal_entrega'] ?? '')
            ?: ($this->canalPreferidoNaConversa($conversa) ?? self::CANAL_EMAIL);

        $destino = (string) $ficha->emailDeAcesso();
        $financeiro = app(Financeiro::class);

        if (! $financeiro->configurado()) {
            return $this->chameALequipe($conversa, 'envio_nao_concluido',
                'O financeiro está desligado (faltam credenciais), e a segunda via da ficha '.$ficha->id.' não saiu.',
                $abertura.'Nosso sistema financeiro está desligado agora, então não consegui emitir a sua via. '
                    .'Já chamei a equipe para resolver isso com você.');
        }

        $id = $ficha->whmcs_id ?: $financeiro->idDoCliente($destino);

        if ($id === null) {
            return $this->chameALequipe($conversa, 'envio_nao_concluido',
                'A ficha '.$ficha->id.' não tem conta localizada no financeiro.',
                $abertura.'Não achei sua conta no nosso financeiro, então não tenho uma fatura para te mandar. '
                    .'Chamei a equipe: ela verifica o vínculo e continua por aqui.');
        }

        $faturas = $financeiro->faturas($id, 10);

        if ($faturas === null) {
            return $this->chameALequipe($conversa, 'envio_nao_concluido',
                'O financeiro não respondeu à consulta de faturas da conta '.$id.'.',
                $abertura.'O financeiro não respondeu agora, então não consegui pegar a sua fatura. Já chamei a equipe — '
                    .'ela te manda a via assim que a conexão voltar.');
        }

        $emAberto = array_values(array_filter(
            $faturas,
            fn (array $f): bool => Veiculacao::faturaEmAberto($f)
        ));

        if ($emAberto === []) {
            $conversa->encerrarAcao();

            return $this->chame($conversa, $abertura.'Boa: não há fatura em aberto no seu cadastro. '
                .'Quando vencer a próxima, ela chega normalmente — e se quiser, é só pedir aqui.');
        }

        // A mais nova das em aberto: a API devolve a lista da mais nova para a mais antiga, e a
        // fatura quitada fica de fora aqui de propósito — segunda via de uma conta paga é cobrança
        // repetida, e é o cliente que sente o erro.
        $fatura = $emAberto[0];
        $enviarNoChat = in_array($canal, [self::CANAL_CHAT, self::CANAL_AMBOS], true);
        $enviarNoEmail = in_array($canal, [self::CANAL_EMAIL, self::CANAL_AMBOS], true);

        if ($enviarNoEmail) {
            try {
                Mail::to($destino)->send(new EmailDaSegundaVia($ficha, $fatura));
            } catch (Throwable $falha) {
                report($falha);

                return $this->chameALequipe($conversa, 'envio_nao_concluido',
                    'O envio da fatura '.$fatura['numero'].' falhou: '.$falha->getMessage(),
                    $abertura.'Seus dados bateram com o cadastro, mas o envio do e-mail não passou agora. Já chamei a equipe '
                        .'para te mandar a via pelo caminho que estiver funcionando.');
            }

            Activity::log('segunda_via_enviada',
                'A segunda via da fatura '.$fatura['numero'].' foi enviada pelo assistente para o e-mail cadastrado.',
                $ficha, [], 'Assistente virtual');
        }

        if ($enviarNoChat) {
            try {
                $this->gerarAnexoPdfNoChat($conversa, $ficha, $fatura, $financeiro, (int) $id);
            } catch (Throwable $falha) {
                report($falha);

                if (! $enviarNoEmail) {
                    return $this->chameALequipe($conversa, 'envio_nao_concluido',
                        'A geração do PDF da fatura '.$fatura['numero'].' no chat falhou: '.$falha->getMessage(),
                        $abertura.'Seus dados bateram com o cadastro, mas não consegui montar o PDF aqui no chat agora. Já chamei a equipe '
                            .'para enviar a sua via rapidinho.');
                }
            }
        }

        $conversa->update(['customer_id' => $ficha->id]);
        $conversa->encerrarAcao();

        $vencimentoTexto = $fatura['vencimento'] ? ', com vencimento em '.$fatura['vencimento']->format('d/m/Y') : '';
        $linkPagamento = trim((string) ($fatura['url'] ?? ''));

        if ($enviarNoChat && $enviarNoEmail) {
            return $this->chame($conversa, $abertura.'Enviei a fatura '.$fatura['numero'].' para o e-mail do seu cadastro'
                .$vencimentoTexto.' e também deixei o PDF anexado aqui no chat'
                .($linkPagamento !== '' ? ' (link de pagamento: '.$linkPagamento.')' : '')
                .'. Confira o arquivo acima e sua caixa de entrada.');
        }

        if ($enviarNoChat) {
            return $this->chame($conversa, $abertura.'Prontinho! Enviei a fatura '.$fatura['numero'].' ('
                .brl((int) ($fatura['total_cents'] ?? 0)).$vencimentoTexto.') em PDF aqui mesmo no chat'
                .($linkPagamento !== '' ? '. Link para pagamento: '.$linkPagamento : '')
                .'. Se quiser, é só avisar que eu também mando para o seu e-mail de cadastro.');
        }

        return $this->chame($conversa, $abertura.'Enviei a fatura '.$fatura['numero'].' para o e-mail do seu cadastro'
            .$vencimentoTexto
            .'. Confira a caixa de entrada — se não estiver lá, me avise que eu chame a equipe. Se preferir receber o PDF por aqui no chat também, é só pedir.');
    }

    /**
     * Detecta em qual canal o cliente deseja receber a fatura (`chat`, `email`, `ambos` ou `null`).
     */
    public static function detectarCanal(string $texto): ?string
    {
        $limpo = self::semAcento($texto);

        if ($limpo === '') {
            return null;
        }

        $querChat = preg_match(
            '/\b(?:no\s+chat|pelo\s+chat|aqui\s+no\s+chat|por\s+aqui|aqui\s+mesmo|aqui\s+em\s+pdf|em\s+pdf\s+aqui|(?:manda|mandar|envia|enviar)\s+(?:por\s+)?aqui|no\s+balao|nesta\s+conversa|nessa\s+conversa|nesse\s+chat|neste\s+chat)\b/',
            $limpo
        ) === 1;

        $querEmail = preg_match(
            '/\b(?:(?:no|pelo|por|para\s+o|pro)\s+(?:meu\s+)?e?\s*mail(?:\s+de\s+cadastro|\s+cadastrado)?|e?\s*mail\s+de\s+cadastro|e?\s*mail\s+cadastrado|(?:manda|mandar|envia|enviar|prefiro|quero)\s+(?:no|por|para|pro)\s+e?\s*mail)\b/',
            $limpo
        ) === 1;

        $querAmbos = preg_match('/\b(?:nos\s+dois|pelos\s+dois|os\s+dois|ambos)\b/', $limpo) === 1 || ($querChat && $querEmail);

        if ($querAmbos) {
            return self::CANAL_AMBOS;
        }

        if ($querChat) {
            return self::CANAL_CHAT;
        }

        if ($querEmail) {
            return self::CANAL_EMAIL;
        }

        return null;
    }

    /** Busca nas falas recentes do cliente se ele já escolheu chat, e-mail ou ambos. */
    private function canalPreferidoNaConversa(Conversa $conversa): ?string
    {
        $falas = $conversa->mensagens()
            ->where('autor', 'cliente')
            ->reorder('id', 'desc')
            ->limit(4)
            ->pluck('corpo')
            ->filter();

        foreach ($falas as $corpo) {
            $canal = self::detectarCanal((string) $corpo);

            if ($canal !== null) {
                return $canal;
            }
        }

        return null;
    }

    /** Remove menções ao canal escolhido para que CPF, data ou placa continuem sendo lidos exatamente pela conferência. */
    private static function limparPreferenciaDeCanal(string $fala): string
    {
        $limpo = preg_replace(
            '/(?:,|\be\b|\bmanda\b|\benvia\b|\benviar\b|\bmandar\b|\bprefiro\b|\bquero\b|\bpode\b)*\s*\b(?:no\s+chat|pelo\s+chat|aqui\s+no\s+chat|por\s+aqui|aqui\s+mesmo|no\s+meu\s+e-?mail|no\s+e-?mail|por\s+e-?mail|pelo\s+e-?mail|nos\s+dois|ambos|por\s+favor)\b.*$/iu',
            '',
            $fala
        );

        return trim((string) $limpo, " \t\n\r\0\x0B,.;:");
    }

    /** Extrai o e-mail quando o cliente escreve endereço + preferência de canal na mesma frase. */
    private static function extrairEmail(string $fala, bool $temCanal): string
    {
        if ($temCanal && preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $fala, $achado) === 1) {
            return trim($achado[0]);
        }

        return trim(str_replace(' ', '', $fala));
    }

    /**
     * Gera o PDF cirúrgico da fatura, grava no disco público e publica o anexo na conversa.
     *
     * @param  array<string, mixed>  $fatura
     */
    private function gerarAnexoPdfNoChat(
        Conversa $conversa,
        Customer $ficha,
        array $fatura,
        Financeiro $financeiro,
        int $whmcsClientId
    ): ConversaMensagem {
        $dadosPdf = $this->montarDadosParaPdf($ficha, $fatura, $financeiro, $whmcsClientId);
        $binario = app(FaturaPdfService::class)->gerarPdfBinario($dadosPdf);
        $numeroSeguro = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($fatura['numero'] ?? $fatura['id'] ?? 'fatura')) ?: 'fatura';
        $nomeArquivo = 'Fatura-'.$numeroSeguro.'.pdf';
        $caminhoRelativo = 'assistente/faturas/'.$conversa->id.'-'.time().'-'.Str::lower(Str::random(6)).'-'.$nomeArquivo;

        Storage::disk('public')->put($caminhoRelativo, $binario);

        $mensagem = ConversaMensagem::create([
            'conversa_id' => $conversa->id,
            'autor' => 'assistente',
            'tipo' => 'arquivo',
            'corpo' => 'Segunda via da fatura #'.$fatura['numero'].' ('.brl((int) ($fatura['total_cents'] ?? 0)).')',
            'midia' => $caminhoRelativo,
            'midia_nome' => $nomeArquivo,
            'midia_mime' => 'application/pdf',
            'midia_bytes' => strlen($binario),
        ]);

        Activity::log('segunda_via_enviada_chat',
            'A segunda via da fatura '.$fatura['numero'].' foi enviada pelo assistente em PDF no chat.',
            $ficha, [], 'Assistente virtual');

        return $mensagem;
    }

    /**
     * Monta o payload completo da fatura e do cliente para gerar o PDF cirúrgico A4.
     *
     * @param  array<string, mixed>  $fatura
     * @return array<string, mixed>
     */
    private function montarDadosParaPdf(
        Customer $ficha,
        array $fatura,
        Financeiro $financeiro,
        int $whmcsClientId
    ): array {
        $detalhes = null;
        $faturaId = (int) ($fatura['id'] ?? 0);

        if ($faturaId > 0) {
            $detalhes = $financeiro->detalharFatura($faturaId, $whmcsClientId);
        }

        $base = is_array($detalhes) ? array_merge($fatura, $detalhes) : $fatura;

        $clientePdf = is_array($base['cliente'] ?? null) ? $base['cliente'] : [];
        $base['cliente'] = [
            'nome' => trim((string) ($clientePdf['nome'] ?? '')) ?: (string) $ficha->nome,
            'empresa' => trim((string) ($clientePdf['empresa'] ?? '')),
            'documento' => trim((string) ($clientePdf['documento'] ?? '')) ?: (string) ($ficha->documento ?? ''),
            'email' => trim((string) ($clientePdf['email'] ?? '')) ?: (string) $ficha->emailDeAcesso(),
            'telefone' => trim((string) ($clientePdf['telefone'] ?? '')) ?: (string) ($ficha->telefone ?? ''),
            'endereco' => trim((string) ($clientePdf['endereco'] ?? '')) ?: (string) ($ficha->endereco ?? ''),
            'cidade' => trim((string) ($clientePdf['cidade'] ?? '')) ?: (string) ($ficha->cidade ?? ''),
            'uf' => trim((string) ($clientePdf['uf'] ?? '')) ?: (string) ($ficha->uf ?? ''),
            'cep' => trim((string) ($clientePdf['cep'] ?? '')) ?: (string) ($ficha->cep ?? ''),
        ];

        return $base;
    }

    /** A ficha do e-mail que a pessoa já deu no atendimento — `null` quando ele não é de ninguém daqui. */
    private static function fichaDoEmailDito(Conversa $conversa): ?Customer
    {
        return blank($conversa->email) ? null : Customer::porEmail((string) $conversa->email);
    }

    /**
     * A resposta que não passou: conta o erro e repete a mesma pergunta. Chegada ao teto, a conversa
     * inteira vai para a equipe — uma conferência abandonada no chat é um cliente sem atendimento.
     */
    private function recusa(Conversa $conversa, string $passo, string $texto): string
    {
        $tentativas = (int) $conversa->acao_tentativas + 1;

        if ($tentativas >= Verificacao::tentativas()) {
            return $this->chameALequipe($conversa, 'verificacao_sem_sucesso',
                'Nenhum dado informado no chat bateu com uma ficha do cadastro.',
                'Não consegui identificar sua conta por aqui. Já chamei a equipe para continuar com você.');
        }

        $conversa->passoDaAcao($passo, (array) $conversa->acao_dados_json, $tentativas);

        return $this->chame($conversa, $texto);
    }

    /** A máquina para e um humano assume: o mesmo caminho do resto do atendimento. */
    private function chameALequipe(Conversa $conversa, string $motivo, string $prova, string $texto): string
    {
        Atendedor::chameEquipe($conversa, $motivo, $prova);
        $conversa->encerrarAcao();

        return $this->chame($conversa, $texto);
    }

    /**
     * O vocativo. A casa pede o nome antes de qualquer assunto, e a conferência — que é feita de
     * frases fixas, não de IA — não podia ser o lugar onde a formalidade se perde.
     */
    private function chame(Conversa $conversa, string $texto): string
    {
        $nome = $conversa->nome;

        if (blank($nome)) {
            return $texto;
        }

        $primeiro = Str::before((string) $nome, ' ');

        return $primeiro.', '.Str::lower(mb_substr($texto, 0, 1)).mb_substr($texto, 1);
    }

    /** O texto do chat, pronto para as regex: minúsculo, sem acento, sem pontuação grudada. */
    private static function semAcento(string $texto): string
    {
        $semAcento = (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);

        return Str::lower(trim(preg_replace('/[^A-Za-z0-9\s]+/', ' ', $semAcento) ?? ''));
    }
}

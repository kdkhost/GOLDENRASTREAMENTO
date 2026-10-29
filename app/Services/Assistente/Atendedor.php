<?php

namespace App\Services\Assistente;

use App\Mail\ConversaEscalada;
use App\Models\Assistente;
use App\Models\Conversa;
use App\Models\ConversaMensagem;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Setting;
use App\Services\Assistente\Contratos\Acao;
use App\Services\Atribuidor;
use App\Support\Notifica;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * O atendimento em si: recebe a fala do cliente e devolve a resposta na mesma conversa.
 *
 * É um lugar só porque o atendimento acontece em três — a bolha do site, a bolha do funil e o
 * chat do chamado — e as três precisam da mesma régua: a mesma persona, a mesma busca de fonte, o
 * mesmo teto de caracteres, a mesma escalada quando não há lastro. Se cada superfície montasse a
 * sua chamada, a primeira diferença de preço entre o site e o portal estaria garantida.
 *
 * Quem já foi assumido pela equipe não recebe mais fala da máquina (`atender()` devolve nulo):
 * dois atendentes na mesma conversa é a maneira mais rápida de o cliente deixar de acreditar em
 * qualquer um dos dois.
 */
final class Atendedor
{
    /**
     * Grava a fala do cliente e responde. A resposta é devolvida para quem monta a tela; quando a
     * máquina está desligada ou a equipe já assumiu, não há resposta e só fica a fala.
     */
    public static function dizer(Conversa $conversa, string $texto, string $tipo = 'texto'): ?ConversaMensagem
    {
        $texto = mb_substr(trim($texto), 0, (int) config('assistente.resposta.caracteres_max', 900) * 3);

        ConversaMensagem::create([
            'conversa_id' => $conversa->id,
            'autor' => 'cliente',
            'tipo' => $tipo,
            'corpo' => $texto,
        ]);

        self::tocarUltimaFala($conversa);

        // Quando quem responde é a equipe — ou a máquina está desligada — a identificação da fala
        // continua valendo: o endereço escrito no chat é o que liga a conversa ao cadastro, e é na
        // caixa do painel que a ficha do cliente aparece ao lado de quem está falando.
        $daMaquina = Assistente::garanta()->ativo
            && $conversa->respondido_por_equipe_em === null
            && $conversa->status !== 'fechada';

        if (! $daMaquina && blank($conversa->email)) {
            $email = Identificacao::extrair($texto);

            if ($email !== null) {
                Identificacao::vincular($conversa, $email);
            }
        }

        return self::atender($conversa);
    }

    /**
     * Responde a última fala do cliente que ainda está sem resposta. Separado de `dizer()` porque
     * a mídia (áudio, imagem, documento) chega antes por outro caminho: a fala é gravada no
     * upload, e a resposta sai quando o arquivo está no disco.
     *
     * A ordem das perguntas abaixo é o atendimento inteiro, e ela não é trocável:
     *
     * **Ação em curso manda.** Quem está no meio de uma conferência de segurança respondeu a uma
     * pergunta definida; desviar essa fala para a IA faria a máquina responder "não entendi" no
     * passo mais delicado da conversa.
     *
     * **Nome e e-mail vêm do que a pessoa diz.** Os dois são aproveitados de qualquer frase, antes
     * de qualquer assunto, porque o nome é o que deixa o atendimento formal e o e-mail é o que
     * deixa a máquina saber de quem é a conta — e um dado gravado tarde ainda chega a tempo da
     * fatura. Quando a fala é só a resposta à pergunta pelo nome ou pelo e-mail, a máquina
     * reconhece com texto fixo e para ali: ninguém fez pergunta nenhuma ao se apresentar.
     *
     * **A ação nova é achada antes de a máquina abrir a boca.** O gatilho é léxico e
     * determinístico: uma IA decidir se "abre ou não" uma conferência de dados é a régua
     * dependente do chute. E ela só abre identificada — atendimento personalizado é o que olha a
     * conta da pessoa, e o que a casa não sabe de quem é, a casa não mexe.
     *
     * **A despedida é o fim, e o fim é regra.** "Obrigado, era isso" não é pergunta: é a conversa
     * encerrada. É nela (e no fecho de uma ação) que a casa pede a nota, e quando a nota já tem dono
     * a máquina despede com o texto da ficha — nunca com o de escalada.
     *
     * **O resto é conversa**, com a fonte obrigatória e o nome no texto de ordem.
     */
    public static function atender(Conversa $conversa): ?ConversaMensagem
    {
        $ficha = Assistente::garanta();

        if (! $ficha->ativo || $conversa->respondido_por_equipe_em !== null || $conversa->status === 'fechada') {
            return null;
        }

        $ultima = $conversa->ultimaMensagem();

        if (! $ultima || $ultima->autor !== 'cliente') {
            return null;
        }

        $pergunta = $ultima->textoVisivel();

        if (blank($pergunta)) {
            return null;
        }

        $acao = Acoes::emCurso($conversa);

        if ($acao !== null) {
            $resposta = self::fale($conversa, $acao->atender($conversa, $pergunta));

            // A ação se encerrou e a conversa continua com a máquina: acabou um atendimento de
            // verdade — é nele, e não numa dúvida respondida pela metade, que a casa quer a nota.
            return $conversa->acao === null ? (self::pecaAValiacao($conversa, $ficha) ?? $resposta) : $resposta;
        }

        $nomeDito = self::guardeONome($conversa, $pergunta, $ficha);
        $emailDito = self::guardeOEmail($conversa, $pergunta);

        $pedida = Acoes::pedidaPela($pergunta) ?? self::acaoEsperandoIdentificacao($conversa);

        if ($pedida !== null) {
            // Nada que olhe a conta da pessoa abre antes de a casa saber de quem é a conta. E o
            // pedido que fica na fila não precisa ser repetido: fechada a identificação, a casa
            // retoma sozinha — enquanto ela falta, a pergunta é o atendimento, não a IA.
            if (Identificacao::exigir() && ! Identificacao::pronta($conversa)) {
                return self::pidaIdentificacao($conversa, $ficha, $pergunta, $pedida);
            }

            return self::fale($conversa, $pedida->abrir($conversa));
        }

        // A resposta à pergunta pelo nome não é pergunta: é apresentação. Reconhecer com uma fala
        // fixa custa zero chamada de IA e não escala a conversa de quem só se apresentou — mandar
        // "Maria Aparecida" ao provedor era o jeito mais educado de dizer "chamei a equipe".
        if ($nomeDito !== null) {
            return self::fale($conversa, $ficha->textoConfirmacaoNome($nomeDito));
        }

        if ($emailDito !== null) {
            // O e-mail achou a ficha do cliente: a casa reconhece a pessoa pelo que já sabe dela,
            // e não pela cortesia de ter guardado um endereço.
            return self::fale($conversa, $emailDito['cliente'] !== null
                ? $ficha->textoConfirmacaoCadastro($conversa->nome)
                : $ficha->textoConfirmacaoEmail($conversa->nome));
        }

        // A nota também se colhe por regra, não por modelo: a última fala foi o pedido e esta é a
        // resposta. Gravada a nota, a casa agradece e a máquina volta ao assunto.
        if (self::acabouDePedirAValiacao($conversa, $ficha)) {
            $nota = Avaliacao::leia($pergunta);

            if ($nota !== null) {
                Avaliacao::grave($conversa, $nota['nota'], $nota['texto']);

                return self::fale($conversa, $ficha->textoAgradecimentoAvaliacao($conversa->nome));
            }
        }

        // A despedida encerra por regra, antes de a máquina abrir a boca: quem escreve "obrigado, era
        // isso" não fez pergunta nenhuma, e mandá-la ao provedor termina em "não sei responder,
        // chamei a equipe" — a pior maneira de fechar um atendimento concluído, ainda mais com a
        // pesquisa aberta na tela esperando nota. É aqui, e não depois da resposta, que ela vira o
        // fim de conversa: sem IA no ar o atendimento ainda se despede, e sem custo de token.
        //
        // A exceção é a saudação de abertura: "boa tarde" de quem chega é o começo, não o fim —
        // pedir nota e se despedir da primeira pessoa que chegou seria o atendimento mais curto.
        $cortesia = Busca::eCortesia($pergunta);

        if (Avaliacao::eDespedida($pergunta) && ! ($cortesia && self::primeiraFalaDoCliente($conversa))) {
            return self::pecaAValiacao($conversa, $ficha)
                ?? self::fale($conversa, $ficha->textoDespedida($conversa->nome));
        }

        $pedeONome = self::vaiPedirONome($conversa);

        // As fontes são achadas uma vez e servem às duas coisas: alimentar a chamada e registrar na
        // fala o que a sustentou. Pedir duas vezes à mesma busca sairia diferente se alguém
        // editasse o conhecimento entre as duas — e a prova gravada deixaria de ser a da resposta.
        $fontes = self::fontesDaConversa($conversa, $ultima, $pergunta);

        $resposta = Motor::responder(new Chamada(
            pergunta: $pergunta,
            instrucoes: self::ordemDaConversa($conversa, $ficha, $pedeONome, $cortesia),
            historico: self::historico($conversa, $ultima),
            fontes: $fontes,
            conversaId: $conversa->id,
            cortesia: $cortesia,
        ));

        $fala = self::graveResposta($conversa, $resposta, $resposta->respondeu() ? self::fontesParaLog($fontes) : []);

        if (! $resposta->respondeu()) {
            self::chameEquipe($conversa, $resposta->motivoDeEscalada ?? 'sem_fonte', $pergunta);
        }

        self::tocarUltimaFala($conversa);

        return $fala;
    }

    /**
     * Um arquivo do cliente — voz, foto ou documento — entra na conversa e é lido.
     *
     * O arquivo é uma fala como qualquer outra: fica gravado com o caminho e o nome original, e o
     * que a máquina entendeu dele vira a resposta da casa. A legenda que veio junto é a pergunta,
     * porque quem manda uma nota fiscal e escreve "essa está paga?" já fez a pergunta certa.
     *
     * Áudio não é lido pela conversa: ele é transcrito primeiro, e a transcrição é a fala. Assim a
     * pergunta falada passa pela mesma identificação e pela mesma busca de fonte de uma pergunta
     * escrita — e a resposta que a pessoa ouve é a resposta que a casa daria por texto.
     *
     * As regras de tipo, extensão e tamanho são da `Midia`, que é onde o painel vira checagem. A
     * recusa sai como `ValidationException` porque é o navegador que precisa ler a frase.
     */
    public static function receberArquivo(Conversa $conversa, UploadedFile $arquivo, string $legenda = ''): ?ConversaMensagem
    {
        $tipo = Midia::tipoDe($arquivo);
        $recusa = Midia::recusa($arquivo, $tipo);

        if ($recusa !== null) {
            throw ValidationException::withMessages(['arquivo' => $recusa]);
        }

        $peca = [
            'caminho' => Midia::guardar($arquivo, $tipo),
            'mime' => (string) ($arquivo->getMimeType() ?: 'application/octet-stream'),
            'nome' => (string) $arquivo->getClientOriginalName(),
        ];

        $legenda = mb_substr(trim(strip_tags($legenda)), 0, 1000);

        $fala = ConversaMensagem::create([
            'conversa_id' => $conversa->id,
            'autor' => 'cliente',
            'tipo' => $tipo,
            'corpo' => $legenda !== '' ? $legenda : null,
            'midia' => $peca['caminho'],
            'midia_nome' => $peca['nome'],
            'midia_mime' => $peca['mime'],
            'midia_bytes' => (int) $arquivo->getSize(),
        ]);

        self::tocarUltimaFala($conversa);

        return self::respondaMidia($conversa, $fala, $tipo, $peca, $legenda);
    }

    /**
     * O fim que o cliente pediu: a conversa fecha e a tela troca o campo de envio pelo convite a
     * começar outra.
     *
     * A nota é pedida antes do fechamento, e não depois, porque a pesquisa só nasce enquanto a
     * conversa ainda é da máquina e ainda não foi parar nas mãos da equipe. Sem nota a pedir, a
     * casa se despede com o texto da ficha — a mesma régua da despedida escrita no chat.
     *
     * A sessão continua apontando para esta conversa: é ela que a tela consulta para pintar o
     * histórico e receber a nota das estrelas. Quem começa outra pede uma conversa nova.
     */
    public static function encerrar(Conversa $conversa): void
    {
        if ($conversa->status === 'fechada') {
            return;
        }

        $ficha = Assistente::garanta();

        if ($ficha->ativo && $conversa->respondido_por_equipe_em === null) {
            if (self::pecaAValiacao($conversa, $ficha) === null) {
                self::fale($conversa, $ficha->textoDespedida($conversa->nome));
            }
        }

        ConversaMensagem::create([
            'conversa_id' => $conversa->id,
            'autor' => 'sistema',
            'tipo' => 'texto',
            'corpo' => 'Conversa encerrada pelo cliente.',
        ]);

        $conversa->encerrarAcao();
        $conversa->registrarStatus('fechada');
        self::tocarUltimaFala($conversa);
    }

    /**
     * A resposta a um arquivo: voz passa pela transcrição e a conversa segue; imagem e documento
     * vão ao provedor na capacidade que sabe lê-los.
     *
     * A leitura de imagem e documento não passa pela tranca de fonte: o que sustenta a resposta é
     * o próprio arquivo que o cliente mandou, não um item do conhecimento — e a capacidade certa já
     * é a que o painel ligou para isso. Sem a máquina no ar, o arquivo fica na conversa para a
     * equipe abrir, e é só isso que o cliente vê.
     */
    private static function respondaMidia(Conversa $conversa, ConversaMensagem $fala, string $tipo, array $peca, string $legenda): ?ConversaMensagem
    {
        $ficha = Assistente::garanta();

        if (! $ficha->ativo || $conversa->respondido_por_equipe_em !== null || $conversa->status === 'fechada') {
            return null;
        }

        if ($tipo === 'audio') {
            $transcricao = self::transcreva($conversa, $peca);

            if ($transcricao === null) {
                self::chameEquipe($conversa, 'midia_nao_lida', 'O áudio enviado pelo cliente não pôde ser transcrito.');

                return self::fale($conversa, 'Recebi o seu áudio, mas não consegui ouvi-lo por aqui. '
                    .'Chamei a equipe — e, se puder, escreva a sua dúvida que eu respondo na hora.');
            }

            $fala->forceFill(['transcricao' => $transcricao])->save();

            // A transcrição é a fala: daqui para frente é o atendimento de sempre, com nome,
            // e-mail, fonte e despedida passando pela mesma régua de uma pergunta escrita.
            return self::atender($conversa);
        }

        $pergunta = $legenda !== '' ? $legenda : match ($tipo) {
            'imagem' => 'O que você vê nesta imagem?',
            default => 'O que este documento diz?',
        };

        // O arquivo é a única fonte desta resposta. Sem esta linha o modelo completa o que falta
        // com conhecimento geral — e um contrato de teste virava um contrato com cláusulas que
        // ninguém escreveu, na frente de quem enviou o arquivo.
        $ordem = self::ordemDaConversa($conversa, $ficha, false);
        $ordem[] = $tipo === 'imagem'
            ? 'A resposta tem que sair do que a imagem mostra. Não invente detalhe que não esteja nela; '
                .'se a imagem não responder à pergunta, diga o que deu para ver e ofereça a equipe.'
            : 'A resposta tem que sair do que o documento enviado diz. Não complete com conhecimento geral '
                .'e não invente cláusula, valor, prazo ou condição que não esteja no arquivo; se o documento '
                .'não responder à pergunta, diga o que encontrou e ofereça a equipe.';

        // Documento que já é texto (txt, csv, docx) vai ao modelo como texto: o Gemini ignora
        // `text/plain` em `inlineData` e responde como se nenhum arquivo tivesse chegado. PDF
        // continua indo pelo anexo, que é o formato que ele lê de verdade.
        $documentos = [];

        if ($tipo === 'arquivo') {
            $textoDoDocumento = Midia::textoDe($peca);

            $documentos[] = $textoDoDocumento === null ? $peca : ['texto' => $textoDoDocumento] + $peca;
        }

        $resposta = Motor::responder(new Chamada(
            pergunta: $pergunta,
            instrucoes: $ordem,
            historico: self::historico($conversa, $fala),
            imagens: $tipo === 'imagem' ? [$peca] : [],
            documentos: $documentos,
            conversaId: $conversa->id,
            capacidade: $tipo === 'imagem' ? 'visao' : 'documento',
        ));

        $respostaGravada = self::graveResposta($conversa, $resposta, []);

        if (! $resposta->respondeu()) {
            self::chameEquipe($conversa, $resposta->motivoDeEscalada ?? 'midia_nao_lida', $pergunta);
        }

        self::tocarUltimaFala($conversa);

        return $respostaGravada;
    }

    /** O que o áudio dizia, ou nulo quando o provedor não deu conta de ouvi-lo. */
    private static function transcreva(Conversa $conversa, array $peca): ?string
    {
        $resposta = Motor::transcrever(new Chamada(
            pergunta: 'Transcreva literalmente o áudio do cliente, em português, sem comentar.',
            audios: [$peca],
            conversaId: $conversa->id,
        ));

        $texto = trim((string) $resposta->texto);

        return $resposta->respondeu() && $texto !== '' ? $texto : null;
    }

    /**
     * A fala da máquina que não saiu de uma IA: passo de ação ou pergunta pelo nome. Ela entra na
     * conversa sem provedor nem modelo gravados, porque a tela precisa mostrar de onde veio cada
     * frase — e esta veio do código, que é justamente o que a equipe não pode discutir.
     */
    private static function fale(Conversa $conversa, string $texto): ConversaMensagem
    {
        $fala = ConversaMensagem::create([
            'conversa_id' => $conversa->id,
            'autor' => 'assistente',
            'tipo' => 'texto',
            'corpo' => mb_substr(trim($texto), 0, (int) config('assistente.resposta.caracteres_max')),
        ]);

        self::tocarUltimaFala($conversa);

        return $fala;
    }

    /**
     * A resposta do provedor virando fala da casa. A prova da chamada (provedor, modelo, tokens e
     * fontes) vai junto porque é por ela que a equipe confere de onde saiu cada frase — e as duas
     * portas da resposta, conversa e arquivo, gravam pelo mesmo caminho.
     */
    private static function graveResposta(Conversa $conversa, Resposta $resposta, array $fontes): ConversaMensagem
    {
        return ConversaMensagem::create([
            'conversa_id' => $conversa->id,
            'autor' => 'assistente',
            'tipo' => 'texto',
            'corpo' => mb_substr(trim($resposta->texto), 0, (int) config('assistente.resposta.caracteres_max')),
            'fontes_json' => $fontes,
            'provedor' => $resposta->provedor,
            'modelo' => $resposta->modelo,
            'tokens_entrada' => $resposta->tokensEntrada,
            'tokens_saida' => $resposta->tokensSaida,
        ]);
    }

    /**
     * O nome de quem fala, aproveitado de qualquer frase. A máquina só tenta adivinhar quando a
     * última fala do assistente foi a pergunta pelo nome: aí "Maria de Jesus" é resposta, e fora
     * desse estado uma resposta solta poderia virar nome de gente por engano.
     *
     * Devolve o nome quando a fala é só ele, resposta direta à pergunta — caso em que não há
     * pergunta nenhuma a mandar ao provedor, e a máquina reconhece a pessoa com a frase da ficha.
     */
    private static function guardeONome(Conversa $conversa, string $pergunta, Assistente $ficha): ?string
    {
        if (filled($conversa->nome)) {
            return null;
        }

        $direta = self::acabouDePerguntarONome($conversa, $ficha);
        $nome = Nome::extrair($pergunta, respostaDireta: $direta);

        if ($nome === null) {
            return null;
        }

        $conversa->update(['nome' => $nome]);

        return $direta && Nome::soONome($pergunta) ? $nome : null;
    }

    /** A última fala do assistente nesta conversa foi exatamente a pergunta pelo nome? */
    private static function acabouDePerguntarONome(Conversa $conversa, Assistente $ficha): bool
    {
        return self::ultimaFalaDaMaquina($conversa) === $ficha->textoPerguntaNome();
    }

    /**
     * O e-mail de quem fala, aproveitado de qualquer frase — inclusive do meio de um assunto, porque
     * "meu nome é Ana, meu e-mail é ana@exemplo.com" chega tudo junto.
     *
     * Ao contrário do nome, o endereço não precisa de a casa ter perguntado para ser confirmado: um
     * e-mail sozinho no chat não se confunde com outra coisa, e fingir não entender o que a pessoa
     * ofereceu seria empurrá-la para a IA com uma frase que já tem dono. Devolve o endereço quando a
     * fala é só ele ou uma apresentação em volta dele ("oi, sou a Maria, meu email é ...") — caso em
     * que não há pergunta nenhuma a mandar ao provedor, e a máquina confirma com a fala fixa da
     * ficha. O endereço nunca vira destino de envio por aqui: quem manda é a ação, e ela manda para
     * a coluna da ficha.
     *
     * @return ?array{email: string, cliente: ?Customer}
     */
    private static function guardeOEmail(Conversa $conversa, string $pergunta): ?array
    {
        if (filled($conversa->email)) {
            return null;
        }

        $email = Identificacao::extrair($pergunta);

        if ($email === null) {
            return null;
        }

        // Quem guarda o endereço é a `Identificacao`, que também procura a ficha do cliente: a
        // conversa fica ligada ao cadastro desde a primeira frase, e a resposta sabe se a pessoa
        // foi reconhecida ou se o e-mail é de quem ainda não é cliente da casa.
        $cliente = Identificacao::vincular($conversa, $email);

        // O nome já foi guardado por `guardeONome()` na linha de cima: passar a conversa atualizada
        // permite à apresentação riscar o nome que a pessoa acabou de dizer e confirmar sem IA.
        return Identificacao::eApresentacao($pergunta, $conversa->nome)
            ? ['email' => $email, 'cliente' => $cliente]
            : null;
    }

    /** A última fala da máquina foi exatamente o pedido da nota? */
    private static function acabouDePedirAValiacao(Conversa $conversa, Assistente $ficha): bool
    {
        if ($conversa->avaliacao_nota !== null) {
            return false;
        }

        return self::ultimaFalaDaMaquina($conversa) === $ficha->textoPedidoAvaliacao($conversa->nome);
    }

    /**
     * A última fala do assistente nesta conversa, crua. É por comparação com ela que a máquina sabe
     * o que acabou de perguntar — o chat é uma requisição por fala, e estado algum seria mais
     * honesto que a própria frase escrita na tela.
     */
    private static function ultimaFalaDaMaquina(Conversa $conversa): ?string
    {
        $pergunta = $conversa->mensagens()
            ->where('autor', 'assistente')
            ->reorder('id', 'desc')
            ->first();

        return $pergunta?->corpo;
    }

    /**
     * As falas que abrem a bolha: a saudação da casa e, logo depois, o pedido de nome. Duas falas e
     * não uma frase só porque a casa atende pelo nome — e quem espera a terceira frase para
     * perguntá-lo já perdeu as duas primeiras para um "Prezado cliente".
     *
     * Quem abre a conversa é a bolha, mas a régua é desta classe: o mesmo teto de
     * `assistente.nome.perguntas_max` que vale no meio do fio vale aqui, e é por isso que as duas
     * falam pela boca de `fale()`.
     *
     * @return array<int, ConversaMensagem>
     */
    public static function abertura(Conversa $conversa, Assistente $ficha): array
    {
        $falas = [];

        if (filled($ficha->saudacao)) {
            $falas[] = self::fale($conversa, (string) $ficha->saudacao);
        }

        if (self::vaiPedirONome($conversa)) {
            $falas[] = self::fale($conversa, $ficha->textoPerguntaNome());
        }

        return $falas;
    }

    /**
     * O pedido de nome desta rodada: ligado quando a casa manda pedir, a pessoa ainda não tem nome
     * na conversa e o teto de `assistente.nome.perguntas_max` não foi gasto. A contagem é gravada
     * aqui, e não na fala, porque quem decide parar de perguntar precisa de prova — e porque a
     * máquina que pergunta o nome no meio de uma conferência de dados é a que entrega a fatura de
     * qualquer um.
     */
    private static function vaiPedirONome(Conversa $conversa): bool
    {
        if (filled($conversa->nome) || ! Nome::pedir()) {
            return false;
        }

        if ((int) $conversa->nome_pedidos >= Nome::perguntasMax()) {
            return false;
        }

        $conversa->update(['nome_pedidos' => (int) $conversa->nome_pedidos + 1]);

        return true;
    }

    /**
     * A porta do atendimento personalizado. Nada que olhe a conta da pessoa abre sem que a casa
     * saiba de quem é a conta: pede o que falta, um por vez, com o teto de cada pergunta, e grava o
     * pedido que ficou na fila (`acao_pendente`).
     *
     * Esgotados os tetos, a conversa vai para a equipe — e não é covardia de máquina: um cliente
     * pedindo segunda via para uma conversa que nunca conseguiu identificá-lo é o cliente sem
     * atendimento. O que a tranca entrega é a pergunta a mais, nunca o envio errado.
     */
    private static function pidaIdentificacao(Conversa $conversa, Assistente $ficha, string $pergunta, Acao $pedida): ConversaMensagem
    {
        $conversa->forceFill(['acao_pendente' => $pedida->codigo(), 'acao_pendente_em' => now()])->save();

        if (blank($conversa->nome) && self::vaiPedirONome($conversa)) {
            return self::fale($conversa, $ficha->textoPerguntaNome());
        }

        if (blank($conversa->email) && self::vaiPedirOEmail($conversa)) {
            return self::fale($conversa, $ficha->textoPerguntaEmail());
        }

        $conversa->forceFill(['acao_pendente' => null, 'acao_pendente_em' => null])->save();

        self::chameEquipe($conversa, 'sem_identificacao', $pergunta);

        return self::fale($conversa, 'Para mexer no que é da sua conta, eu preciso saber de quem é a conta. '
            .'Como você não quis me dizer, já chamei a equipe — ela continua com você por aqui.');
    }

    /**
     * A ação que ficou na fila da identificação, se ainda vale.
     *
     * Ela é olhada em toda fala, porque quem pediu a segunda via e ainda não tem e-mail na conversa
     * está pedindo atendimento personalizado — e a pergunta certa é a do cadastro, não uma IA
     * respondendo teoria de rastreador. O teto de cada pergunta (`assistente.contato.perguntas_max`)
     * é o que limita a insistência, e vencido o prazo da conferência (`Verificacao::minutos`) o
     * pedido vence junto: retomar uma segunda via dita uma hora atrás, noutro assunto, seria a
     * máquina agindo por conta própria.
     */
    private static function acaoEsperandoIdentificacao(Conversa $conversa): ?Acao
    {
        if (blank($conversa->acao_pendente)) {
            return null;
        }

        $vence = $conversa->acao_pendente_em?->addMinutes(Verificacao::minutos());
        $acao = $vence !== null && $vence->greaterThan(now()) ? Acoes::porCodigo($conversa->acao_pendente) : null;

        if ($acao === null || ! $acao->ligada()) {
            $conversa->forceFill(['acao_pendente' => null, 'acao_pendente_em' => null])->save();

            return null;
        }

        return $acao;
    }

    /**
     * O pedido de e-mail desta rodada: ligado quando a casa manda pedir, a conversa ainda não tem
     * endereço e o teto de `assistente.contato.perguntas_max` não foi gasto. A conta é gravada na
     * conversa, e não na fala, porque quem decide parar de perguntar precisa de prova.
     */
    private static function vaiPedirOEmail(Conversa $conversa): bool
    {
        if (filled($conversa->email) || ! Identificacao::pedir()) {
            return false;
        }

        if ((int) $conversa->email_pedidos >= Identificacao::perguntasMax()) {
            return false;
        }

        $conversa->update(['email_pedidos' => (int) $conversa->email_pedidos + 1]);

        return true;
    }

    /**
     * A fala é a primeira do cliente nesta conversa? Serve à saudação ambígua: "boa tarde" de
     * quem chega abre o atendimento, e "boa tarde" no meio dele fecha — a mesma frase, dois
     * significados, decididos por onde ela está.
     */
    private static function primeiraFalaDoCliente(Conversa $conversa): bool
    {
        return $conversa->mensagens()->where('autor', 'cliente')->count() <= 1;
    }

    /**
     * A fala de agradecimento depois de uma nota dada por outro caminho que não o chat — hoje, o
     * toque nas estrelas da bolha. É pública porque quem grava a nota não é este atendedor, mas a
     * régua de quem responde é: a frase continua sendo a da ficha, e a conversa continua da máquina.
     */
    public static function agradeceAValiacao(Conversa $conversa): ConversaMensagem
    {
        return self::fale($conversa, Assistente::garanta()->textoAgradecimentoAvaliacao($conversa->nome));
    }

    /**
     * A pesquisa de satisfação, dita no fim de um atendimento — o da ação que se encerrou ou o da
     * despedida escrita no chat. Pede uma vez só, porque `avaliacao_pedida_em` é a prova, e só pede
     * enquanto a conversa é da máquina: quem foi assumido por um humano avaliaria quem não atendeu.
     *
     * A nota não passa por IA nenhuma: ela entra pelo toque na bolha ou pelo número escrito aqui, e
     * os dois caminhos gravam as mesmas colunas.
     */
    private static function pecaAValiacao(Conversa $conversa, Assistente $ficha): ?ConversaMensagem
    {
        if (! Avaliacao::horaDePedir($conversa)) {
            return null;
        }

        $conversa->forceFill(['avaliacao_pedida_em' => now()])->save();

        return self::fale($conversa, $ficha->textoPedidoAvaliacao($conversa->nome));
    }

    /**
     * A ordem de sistema desta conversa: a persona da ficha mais o que se sabe de quem está do
     * outro lado. É aqui que o nome entra na boca da máquina, porque o atendimento formal da casa
     * não pode depender de a IA ter lido a frase em que a pessoa se apresentou.
     */
    private static function ordemDaConversa(Conversa $conversa, Assistente $ficha, bool $pedeONome, bool $cortesia = false): array
    {
        $ordem = explode("\n", $ficha->instrucoes());

        if ($cortesia) {
            $ordem[] = 'Esta fala é só uma saudação: cumprimente de volta em uma frase e diga como você pode ajudar. '
                .'Não diga que não sabe, não chame a equipe e não invente preço, prazo, cobertura, especificação, desconto ou condição.';
        } else {
            // A persona escrita na ficha pode ter sido trocada por outra; esta linha é do código e
            // vale sempre: a conversa é um fio só, e é ele que faz a Ana parecer uma pessoa.
            $ordem[] = 'Esta é uma conversa em andamento: continue de onde ela está, sem repetir a saudação, '
                .'a pergunta que a pessoa acabou de responder ou o que você já disse.';
        }

        if (filled($conversa->nome)) {
            $ordem[] = 'A pessoa do outro lado se chama '.$conversa->nome.'. Trate-a pelo nome quando fizer sentido, sem repeti-lo em toda frase.';
        } elseif ($pedeONome) {
            $ordem[] = 'Você ainda não sabe o nome desta pessoa: pergunte-o nesta resposta, com naturalidade e uma única vez, e siga atendendo o assunto.';
        } elseif (Nome::pedir()) {
            $ordem[] = 'Esta pessoa não quis se identificar. Atenda sem nome e não volte a pedir.';
        }

        if (filled($conversa->email)) {
            $ordem[] = 'O e-mail desta pessoa já foi dito: não o peça de novo, não o escreva na resposta e não o use '
                .'para prometer nada — quem localiza a conta e confere os dados é o sistema, em outra etapa.';
        } elseif (Identificacao::exigir() && Identificacao::pedir()) {
            $ordem[] = 'Sem o e-mail do cadastro, nada da conta desta pessoa pode ser enviado, alterado ou localizado. '
                .'Não diga que fez nada disso: responda a dúvida geral e diga que o atendimento personalizado segue com a equipe.';
        }

        return $ordem;
    }

    /**
     * O visitante cadastrou-se pelo formulário da casa com a bolha aberta no mesmo navegador: a
     * conversa ganha o lead e a fala de sistema que amarra as duas. É o único ponto em que o
     * atendimento anônimo vira negócio — e é aqui, e não no formulário, porque o formulário não
     * precisa saber que existe um robô de chat.
     */
    public static function fezContato(Lead $lead, Request $request): void
    {
        $conversa = Conversa::naSessao($request);

        if ($conversa === null || $conversa->lead_id === $lead->id) {
            return;
        }

        $conversa->update([
            'lead_id' => $lead->id,
            'nome' => $conversa->nome ?: Str::limit((string) $lead->name, 90, ''),
            'contato' => $conversa->contato ?: (string) ($lead->phone !== '-' ? $lead->phone : $lead->email),
        ]);

        ConversaMensagem::create([
            'conversa_id' => $conversa->id,
            'autor' => 'sistema',
            'tipo' => 'texto',
            'corpo' => 'O cadastro virou o lead '.$lead->id.' ('.Atribuidor::rotuloCanal((string) $lead->canal)
                .'). A equipe continua por aqui.',
        ]);

        self::tocarUltimaFala($conversa);
    }

    /**
     * As fontes desta fala — ouvindo a fala anterior quando a atual é curta.
     *
     * Conversa de gente não repete o assunto a cada frase: depois de "quanto custa o rastreador?",
     * um "e a instalação?" é a mesma pergunta em duas partes. Uma busca feita só com a segunda
     * metade não acharia nada e a Ana escalaria no meio de um assunto que ela já estava
     * respondendo. Só a fala curta puxa a anterior, e só quando a busca sozinha não achou nada:
     * uma pergunta completa continua sendo buscada exatamente como foi escrita.
     *
     * @return array<int, array{id: int, titulo: string, texto: string, procedencia: string, pontuacao: float, ancorado: bool}>
     */
    private static function fontesDaConversa(Conversa $conversa, ConversaMensagem $atual, string $pergunta): array
    {
        $fontes = Busca::fontes($pergunta);

        if ($fontes !== [] || Busca::eCortesia($pergunta) || count(Busca::palavrasDaPergunta($pergunta)) >= 4) {
            return $fontes;
        }

        $anterior = $conversa->mensagens()
            ->where('autor', 'cliente')
            ->where('id', '<', $atual->id)
            ->reorder('id', 'desc')
            ->first();

        return $anterior === null ? $fontes : Busca::fontes($pergunta.' '.$anterior->textoVisivel());
    }

    /**
     * As falas que o provedor enxerga antes da pergunta: as últimas, contadas de trás para frente,
     * sem a fala que está sendo respondida — ela já vai no campo `pergunta` da chamada.
     *
     * @return array<int, array{autor: string, texto: string}>
     */
    private static function historico(Conversa $conversa, ConversaMensagem $atual): array
    {
        $limite = (int) config('assistente.resposta.historico_mensagens');

        return $conversa->mensagens()
            ->where('id', '<', $atual->id)
            ->reorder('id', 'desc')
            ->take($limite)
            ->get()
            ->reverse()
            ->map(fn (ConversaMensagem $fala): array => ['autor' => $fala->autor, 'texto' => $fala->textoVisivel()])
            ->values()
            ->all();
    }

    /** As fontes achadas, do jeito que a tela de atendimento precisa lê-las para conferir. */
    private static function fontesParaLog(array $fontes): array
    {
        return array_map(
            fn (array $fonte): array => ['titulo' => $fonte['titulo'], 'procedencia' => $fonte['procedencia']],
            $fontes
        );
    }

    /**
     * A máquina parou: a conversa entra na fila da equipe e o e-mail de atendimento é avisado — o
     * mesmo endereço que recebe chamado e pedido, porque duas caixas de entrada para a mesma
     * equipe é como se perde um aviso. O envio é uma tentativa: sem SMTP no ar a conversa continua
     * marcada como pendente na caixa do painel, e o cliente já tem a resposta que a tela deu.
     *
     * É público porque a parada não vem só do `Motor`: uma ação que não conseguiu enviar precisa do
     * mesmo caminho, e o que muda entre os dois é apenas a prova de por que parou.
     */
    public static function chameEquipe(Conversa $conversa, string $motivo, string $prova): void
    {
        $conversa->escalar($motivo);

        // O sino é o único aviso que chega mesmo sem SMTP: a conversa já está esperando na caixa
        // do painel, e é ali que a equipe a encontra quando abre o sistema.
        Notifica::disparar(
            'conversa_escalada',
            'Conversa esperando a equipe: '.($conversa->nome ?: 'visitante do site'),
            $prova,
            (string) $conversa->id,
            [
                'conversa_id' => $conversa->id,
                'motivo' => $motivo,
                'visitante' => $conversa->nome,
                'canal' => $conversa->canal,
                'pagina' => $conversa->pagina,
            ],
            'conversa_escalada:'.$conversa->id,
        );

        $destino = Setting::get('contato_email', (string) config('golden.email'));

        if (! (bool) config('assistente.resposta.avisar_por_email') || $destino === '' || $destino === null) {
            return;
        }

        try {
            Mail::to($destino)->queue(new ConversaEscalada($conversa, $motivo, $prova));
        } catch (Throwable $falha) {
            report($falha);
        }
    }

    private static function tocarUltimaFala(Conversa $conversa): void
    {
        $conversa->forceFill(['ultima_fala_em' => now()])->save();
    }
}

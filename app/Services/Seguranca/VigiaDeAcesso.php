<?php

namespace App\Services\Seguranca;

use App\Mail\TentativasDeLogin;
use App\Models\Activity;
use App\Models\SessaoDoPainel;
use App\Models\User;
use App\Support\Notifica;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * O porteiro do painel: conta quantas vezes bateram, fecha a porta e deixa rastro.
 *
 * O `throttle:10,1` da rota protege o servidor, não a conta — ele é medido por IP, então um
 * atacante com lista de senhas vazadas roda dez tentativas por minuto de uma rede, depois troca de
 * rede e recomeça, enquanto a conta atacada continua abrindo para quem souber a senha. Aqui a
 * contagem é por **e-mail e IP juntos**: a conta trava de verdade, e um escritório inteiro atrás do
 * mesmo roteador não trava a ficha de um colega.
 *
 * O que mais importa nesta classe não é o bloqueio — é o **rastro**. Antes dela, uma senha errada
 * não deixava nenhum vestígio: nem na auditoria, nem no sino, nem na caixa de quem é dono da conta.
 * Força-bruta contra um painel que guarda leads, fichas de cliente e financeiro é o tipo de visita
 * que a casa precisa saber que recebeu.
 *
 * O aviso por e-mail sai só quando o limite estoura, nunca a cada erro: um aviso por digitação
 * errada é o aviso que a pessoa passa a ignorar, e aí o importante se perde no barulho.
 */
class VigiaDeAcesso
{
    /** Quantos segundos separam duas gravações de "esta sessão está viva". */
    private const RENOVACAO = 60;

    /**
     * Segundos que faltam para a porta reabrir, ou `null` quando ainda há tentativas livres.
     *
     * Olha as duas travas — a da conta e a da rede — e devolve a pior: bloquear só por conta permite
     * esgotar a lista de senhas de um endereço distribuído; bloquear só por IP permite ao mesmo script
     * varrer a lista de endereços da casa sem nunca parar. Um escritório inteiro atrás do mesmo
     * roteador não trava a ficha de um colega, porque a trava do IP só fecha depois de bater o limite
     * de tentativas vindas dele.
     *
     * A trava é um balde à parte do contador: o contador mede a janela de ataque (15 minutos, por
     * exemplo), e a trava mede o tempo de castigo (30). No `RateLimiter` do Laravel o prazo é gravado
     * no primeiro toque do balde e não se estica mais, então as duas durações não cabem num só balde.
     */
    public function bloqueadoPor(string $email, string $ip): ?int
    {
        $restam = [];

        foreach ([$this->travaConta($email), $this->travaIp($ip)] as $chave) {
            if (RateLimiter::tooManyAttempts($chave, 1)) {
                $restam[] = RateLimiter::availableIn($chave);
            }
        }

        return $restam === [] ? null : max($restam);
    }

    /** Uma tentativa a mais nas duas contagens, o rastro na auditoria e o aviso quando estoura. */
    public function falhou(?User $usuario, string $email, Request $request): void
    {
        $limite = $this->limite();
        $contas = [$this->chaveConta($email), $this->chaveIp((string) $request->ip())];

        foreach ($contas as $chave) {
            RateLimiter::hit($chave, $this->janela());
        }

        Activity::log(
            'login_falhou',
            'Tentativa de acesso ao painel recusada para '.$email.'.',
            $usuario,
            ['email' => $email, 'ip' => $request->ip(), 'agente' => Str::limit((string) $request->userAgent(), 160)],
            $email,
        );

        $tentativas = RateLimiter::attempts($contas[0]);
        $estourou = $tentativas >= $limite
            || RateLimiter::attempts($contas[1]) >= $limite;

        if ($estourou) {
            // Fecha de verdade, dos dois lados: quem tenta pelo IP também perde a rede, senão o
            // mesmo script basta digitar outro e-mail de colega para continuar batendo.
            RateLimiter::hit($this->travaConta($email), $this->bloqueio());
            RateLimiter::hit($this->travaIp((string) $request->ip()), $this->bloqueio());
        }

        if ($tentativas < $limite) {
            return;
        }

        // Um fato, um aviso: a mesma conta não enche o sino a cada tentativa seguinte.
        $chave = 'acesso:'.$email.':'.now()->format('YmdH');
        $minutos = intdiv($this->bloqueio(), 60);

        Notifica::disparar(
            'acesso_suspeito',
            $tentativas === $limite ? 'Conta travada: '.$email : 'A conta de '.$email.' segue sendo testada',
            $tentativas.' tentativas de senha recusadas na última janela, vindo de '.(string) $request->ip()
                .'. O acesso desse endereço fica fechado por '.$minutos.' minuto(s).',
            null,
            ['email' => $email, 'ip' => (string) $request->ip(), 'tentativas' => $tentativas],
            $chave,
        );

        if ($tentativas === $limite && $usuario !== null) {
            $this->avisarDono($usuario, $request, $limite);
        }
    }

    /** Acertou: zera contagens e travas, porque a janela é de ataque, não de histórico. */
    public function acertou(string $email, Request $request): void
    {
        foreach ([$this->chaveConta($email), $this->chaveIp((string) $request->ip()),
            $this->travaConta($email), $this->travaIp((string) $request->ip())] as $chave) {
            RateLimiter::clear($chave);
        }
    }

    /**
     * Zera a contagem de uma conta, sem tocar na da rede.
     *
     * É o botão "destravar" da tela de Segurança. Ele não apaga o IP de propósito: a pessoa voltou a
     * digitar a senha errada dez vezes do escritório, e a próxima pessoa que tentar por aquela mesma
     * rede vai esbarrar na trava que as tentativas dela construíram. Destravar a conta do colega não
     * destrava o atirador.
     */
    public function destravar(string $email): void
    {
        RateLimiter::clear($this->chaveConta($email));
        RateLimiter::clear($this->travaConta($email));
    }

    /**
     * Quantas tentativas já foram recusadas neste endereço, para a tela de Segurança mostrar.
     *
     * O contador tem janela, então "já" é "nesta janela", e é isso que a pessoa quer saber.
     */
    public function tentativasNaJanela(string $email): int
    {
        return RateLimiter::attempts($this->chaveConta($email));
    }

    /**
     * O segundo fator também é uma porta, e também se conta.
     *
     * Seria um contrassenso travar a senha com cinco tentativas e deixar o código de seis dígitos —
     * que tem um espaço de busca de um milhão — ser testado sem limite nenhum. A contagem é por
     * conta, e o limite é o mesmo da tela de Configurações.
     */
    public function tentativasDoCodigo(User $usuario): int
    {
        return RateLimiter::attempts($this->chaveCodigo($usuario->id));
    }

    public function codigoFalhou(User $usuario): int
    {
        RateLimiter::hit($this->chaveCodigo($usuario->id), $this->janela());

        return $this->tentativasDoCodigo($usuario);
    }

    public function codigoAcertou(User $usuario): void
    {
        RateLimiter::clear($this->chaveCodigo($usuario->id));
    }

    /** Segundos que faltam para o campo do código voltar a aceitar tentativa, ou `null`. */
    public function codigoBloqueadoPor(User $usuario): ?int
    {
        return RateLimiter::tooManyAttempts($this->chaveCodigo($usuario->id), $this->limite())
            ? RateLimiter::availableIn($this->chaveCodigo($usuario->id))
            : null;
    }

    /**
     * Registra a janela aberta e avisa se ela é a primeira vinda de um aparelho nunca visto.
     *
     * Também derruba as sessões mais antigas que o limite da tela de Configurações permite — é o
     * que faz "entre no painel do celular e o notebook do escritório saia" funcionar sozinho.
     *
     * O carimbo `janela_painel` vai dentro do **conteúdo** da sessão, e é o **número da linha**
     * aberta no banco, não o endereço da sessão: é ele que diz, no pedido seguinte, qual das janelas
     * desta conta está falando. Uma sessão que não tem carimbo é anterior a esta versão (ou de teste)
     * e é adotada, nunca expulsada.
     */
    public function abriuSessao(User $usuario, Request $request): void
    {
        $id = $request->session()->getId();
        $ip = (string) $request->ip();
        $agente = Str::limit((string) $request->userAgent(), 160, '');
        $carimbo = SessaoDoPainel::carimbo($ip, $agente);

        $nova = ! SessaoDoPainel::where('user_id', $usuario->id)->where('carimbo', $carimbo)->exists();

        $aberta = SessaoDoPainel::create([
            'user_id' => $usuario->id,
            'sessao' => $id,
            'carimbo' => $carimbo,
            'ip' => $ip,
            'agente' => $agente,
            'ultimo_visto_em' => now(),
        ]);

        $request->session()->put('janela_painel', $aberta->id);

        $this->apodar($usuario, $aberta->id);

        if ($nova) {
            Activity::log(
                'login_dispositivo_novo',
                'Acesso a partir de um aparelho novo: '.$aberta->dispositivo().' ('.$ip.').',
                $usuario,
                ['ip' => $ip, 'agente' => $agente],
            );
        }
    }

    /**
     * A sessão de agora está autorizada a continuar?
     *
     * Uma linha que não existe mais foi encerrada pela própria pessoa (ou pelo teto de aparelhos),
     * e uma linha parada além do tempo de inatividade é uma aba esquecida — nas duas contas, o
     * acesso se fecha ali, não quando a pessoa lembrar de sair.
     *
     * Quem entrou antes desta versão, ou um teste que monta o usuário sem abrir sessão de
     * verdade, não tem carimbo: adota a janela atual em vez de expulsar quem já estava trabalhando.
     */
    public function podeContinuar(User $usuario, Request $request): bool
    {
        $carimbo = $request->session()->get('janela_painel');

        if ($carimbo === null) {
            $this->abriuSessao($usuario, $request);

            return true;
        }

        // A linha não está mais lá: a própria pessoa fechou a janela, o teto de aparelhos a derrubou,
        // ou o carimbo é de outra conta. Nas três, quem bate à porta não é mais um acesso autorizado.
        $sessao = SessaoDoPainel::where('id', $carimbo)->where('user_id', $usuario->id)->first();

        if ($sessao === null) {
            return false;
        }

        $ociosa = (int) config('seguranca.sessao_minutos', 120);

        if ($sessao->ultimo_visto_em !== null && $sessao->ultimo_visto_em->lt(now()->subMinutes($ociosa))) {
            $sessao->delete();

            return false;
        }

        if ($sessao->ultimo_visto_em === null || $sessao->ultimo_visto_em->lt(now()->subSeconds(self::RENOVACAO))) {
            $sessao->forceFill(['ultimo_visto_em' => now()])->save();
        }

        return true;
    }

    /** Fecha uma janela concreta. O acesso daquele aparelho cai na próxima batida da middleware. */
    public function encerrar(User $usuario, int $id): bool
    {
        return SessaoDoPainel::where('user_id', $usuario->id)->where('id', $id)->delete() > 0;
    }

    /** Fecha todas as outras janelas e guarda a de agora — o botão "sair dos outros aparelhos". */
    public function encerrarAsOutras(User $usuario, Request $request): int
    {
        $agora = $request->session()->get('janela_painel');

        return SessaoDoPainel::where('user_id', $usuario->id)
            ->when($agora, fn ($q) => $q->where('id', '!=', $agora))
            ->delete();
    }

    /**
     * Corta as janelas que passaram do teto de aparelhos, guardando a que acabou de abrir.
     *
     * A protegida é explícita porque ela conta como uma das permitidas: com teto 3, ficam ela mais
     * duas vivas. Sem esse cuidado, a janela que a pessoa usa agora seria a primeira candidata a
     * cair quando o teto já estava estourado.
     */
    private function apodar(User $usuario, ?int $protegida = null): void
    {
        $teto = (int) config('seguranca.sessoes_simultaneas', 5);

        if ($teto <= 0) {
            return;
        }

        $sobras = SessaoDoPainel::where('user_id', $usuario->id)
            ->when($protegida, fn ($q) => $q->where('id', '!=', $protegida))
            ->orderByDesc('ultimo_visto_em')
            ->skip(max(0, $teto - ($protegida === null ? 0 : 1)))
            ->take(50)
            ->pluck('id');

        if ($sobras->isNotEmpty()) {
            SessaoDoPainel::whereIn('id', $sobras)->delete();
        }
    }

    private function avisarDono(User $usuario, Request $request, int $limite): void
    {
        try {
            Mail::to($usuario->email)->queue(new TentativasDeLogin(
                $usuario,
                (string) $request->ip(),
                Str::limit((string) $request->userAgent(), 160, ''),
                $limite,
                (int) (config('seguranca.bloqueio_minutos', 30)),
                now()->format('d/m/Y H:i'),
            ));
        } catch (Throwable) {
            // Sem correio configurado o aviso continua no sino: o registro não pode depender do
            // transporte, e uma instalação recém-clonada ainda não tem SMTP.
        }
    }

    /** O teto de tentativas da tela de Configurações, para a tela e para o aviso "restam N". */
    public function limite(): int
    {
        return max(1, (int) config('seguranca.tentativas', 5));
    }

    private function chaveConta(string $email): string
    {
        return 'acesso-conta:'.sha1(Str::lower(trim($email)));
    }

    private function chaveIp(string $ip): string
    {
        return 'acesso-ip:'.$ip;
    }

    private function travaConta(string $email): string
    {
        return 'trava-conta:'.sha1(Str::lower(trim($email)));
    }

    private function travaIp(string $ip): string
    {
        return 'trava-ip:'.$ip;
    }

    private function chaveCodigo(int|string $usuarioId): string
    {
        return 'acesso-codigo:'.$usuarioId;
    }

    /** A janela de contagem, em segundos: o número da tela está em minutos, o balde em segundos. */
    private function janela(): int
    {
        return max(60, ((int) config('seguranca.janela_minutos', 15)) * 60);
    }

    /** O castigo, em segundos. O `max` protege de zero, não de minuto: multiplicar antes do teto. */
    private function bloqueio(): int
    {
        return max(60, ((int) config('seguranca.bloqueio_minutos', 30)) * 60);
    }
}

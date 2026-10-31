<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncGoogleAdsMetrics;
use App\Models\Activity;
use App\Models\GoogleAdsAccount;
use App\Models\User;
use App\Services\GoogleAds\GoogleAdsService;
use App\Support\ChavesDeIntegracao;
use App\Support\GoogleAdsCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

/**
 * O consentimento que liga a conta de Google Ads de um usuário do painel.
 *
 * São duas etapas e nada mais: `redirect` manda a pessoa para o Google e `callback` recebe o
 * token de volta. O que o callback faz com o token é o ponto da integração em que um erro
 * fica caro — por isso ele não assume nada: pergunta ao Google quais contas aquele token
 * alcança, escolhe uma, e só grava depois de saber o número real da conta.
 *
 * Reconectar é atualizar, nunca duplicar. A chave única é (usuário, `customer_id`), e a
 * busca é por ela: quem autoriza de novo — para trocar a senha da conta Google, ou porque o
 * consentimento expirou — recebe a mesma fila com o token novo, e a métrica que já está no
 * cache continua no lugar até a próxima sincronização.
 */
class GoogleAdsAuthController extends Controller
{
    /** A frase dita quando falta credencial: nomeia o que falta e diz onde se preenche. */
    private const SEM_CREDENCIAL = 'Faltam credenciais do Google Ads: %s. Preencha em '
        .ChavesDeIntegracao::CAMINHO_NO_PAINEL.'.';

    public function __construct(private GoogleAdsService $servico) {}

    /** Manda o usuário para a tela de consentimento do Google. */
    public function redirect(): SymfonyResponse|RedirectResponse
    {
        $faltantes = GoogleAdsService::credenciaisFaltantes();

        if ($faltantes->isNotEmpty()) {
            return redirect()->route('admin.google-ads.index')
                ->with('error', sprintf(self::SEM_CREDENCIAL, $faltantes->implode(', ')));
        }

        return Socialite::driver('google-ads')->redirect();
    }

    /**
     * Recebe o código do Google, transforma em token e conecta a conta.
     *
     * Toda falha deste momento volta para a tela com o motivo em português: a pessoa precisa
     * saber se errou a senha do Google, se recusou o escopo de anúncios ou se o servidor é
     * que está sem credencial — são três caminhos diferentes para ela.
     */
    public function callback(Request $request): RedirectResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return redirect()->route('login')->with('error', 'Faça login no painel antes de conectar a conta de Google Ads.');
        }

        if ($request->has('error')) {
            Activity::log('google_ads_consentimento_negado', "O usuário desistiu da conexão de Google Ads (".str_replace('_', ' ', (string) $request->input('error')).").", $usuario);

            return redirect()->route('admin.google-ads.index')
                ->with('warning', 'A conexão foi cancelada no Google. Nenhum acesso foi guardado.');
        }

        // O par OAuth entra na conta desta checagem: um consentimento que termina em erro de API
        // é pior que nenhum consentimento — a pessoa deu acesso ao painel sem nada em troca.
        $faltantes = GoogleAdsService::credenciaisFaltantes();

        if ($faltantes->isNotEmpty()) {
            return redirect()->route('admin.google-ads.index')
                ->with('error', sprintf(self::SEM_CREDENCIAL, $faltantes->implode(', ')));
        }

        try {
            $autorizado = Socialite::driver('google-ads')->user();
            $token = (string) $autorizado->refreshToken;
        } catch (Throwable $e) {
            return redirect()->route('admin.google-ads.index')
                ->with('error', 'O Google não concluiu a autorização: '.$e->getMessage());
        }

        if ($token === '') {
            return redirect()->route('admin.google-ads.index')->with('error', 'O Google devolveu o acesso sem token de renovação. Feche a sessão do Google no navegador e tente conectar de novo, aceitando todos os pedidos de permissão.');
        }

        try {
            $conta = $this->conectar($usuario, $token);
        } catch (Throwable $e) {
            return redirect()->route('admin.google-ads.index')
                ->with('error', 'Não foi possível ler as contas deste acesso: '.$e->getMessage());
        }

        Activity::log('google_ads_conectada', "Conta de Google Ads {$conta->numeroConta()} conectada.", $conta);

        return redirect()->route('admin.google-ads.index')->with('success', "Conta {$conta->nomeTela()} conectada. A primeira sincronização já está a caminho.");
    }

    /**
     * Escolhe a conta, busca o nome e grava (ou atualiza) a fila do usuário.
     *
     * A preferência é o MCC do ambiente, quando o token o alcança: quem conecta pelo gerente
     * quer o gerente, e as contas da árvore virão depois pelo seletor da tela — sem novo
     * consentimento, porque o token vale para toda a árvore.
     */
    private function conectar(User $usuario, string $token): GoogleAdsAccount
    {
        $alcanca = $this->servico->contasAutorizadas($token);

        if ($alcanca === []) {
            throw new RuntimeException('este acesso não alcança nenhuma conta de Google Ads — pode ser conta pessoal, sem anunciante criado.');
        }

        $mcc = (string) config('google_ads.login_customer_id');
        $escolhida = in_array($mcc, $alcanca, true) ? $mcc : $alcanca[0];

        // O nome e a moeda vêm do Google; se essa consulta menor falhar, a conta ainda está
        // conectada e útil, e o painel mostra o número em vez do descritivo.
        $detalhes = rescue(
            fn (): array => $this->servico->detalhesDaConta($escolhida, $token),
            ['nome' => null, 'moeda' => null, 'gerente' => false],
            false,
        );

        $conta = GoogleAdsAccount::firstOrNew([
            'user_id' => $usuario->id,
            'customer_id' => $escolhida,
        ]);

        $conta->fill([
            'refresh_token' => $token,
            'descriptive_name' => $detalhes['nome'],
            'currency_code' => $detalhes['moeda'],
            'status' => GoogleAdsAccount::ATIVO,
            'motivo_desconexao' => null,
        ])->save();

        // A cache velha é da credencial velha: a mesma conta, com o token novo, pode ser outra
        // pessoa do outro lado da autorização.
        GoogleAdsCache::esquecer($conta);

        SyncGoogleAdsMetrics::dispatch($conta->id, (int) config('google_ads.dias_padrao', 30));

        return $conta;
    }
}

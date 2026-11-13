<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncGoogleAdsMetrics;
use App\Models\Activity;
use App\Models\GoogleAdsAccount;
use App\Models\GoogleAdsPlano;
use App\Services\GoogleAds\Excecoes\GoogleAdsFalhou;
use App\Services\GoogleAds\GoogleAdsEditor;
use App\Services\GoogleAds\GoogleAdsService;
use App\Services\GoogleAds\PlanoDeCampanha;
use App\Support\Envio;
use App\Support\GoogleAdsCache;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * A campanha que já está no Google Ads, gerida pelo painel: a ficha dela como o Google a tem
 * agora, ativar e pausar, editar nome/orçamento/regiões/negativas, grupos (criar, editar,
 * pausar, remover) e os recursos vinculados (imagem, sitelink, frase de destaque).
 *
 * Toda tela parte de uma leitura viva (`GoogleAdsEditor::ler`), nunca da cache de métricas:
 * é a leitura que prova que a campanha, o grupo e o recurso pertencem à conta da sessão. Um
 * identificador que não veio dessa leitura não é escrito — é o que impede um número trocado
 * no formulário de mexer na campanha de outra conta.
 *
 * Mudar o status da campanha é a única escrita que passa pelo cliente oficial: primeiro
 * `validate_only`, que percorre permissão, status e FieldMask sem tocar na campanha; só
 * depois, se o Google aprovou, a mesma operação de verdade. Uma conta real de anunciante não
 * é lugar de experimentar.
 */
class GoogleAdsCampanhaController extends Controller
{
    public function __construct(private GoogleAdsService $servico, private GoogleAdsEditor $editor) {}

    public function show(Request $request, string $campanha): View|RedirectResponse
    {
        $aberta = $this->abrir($request, $campanha);

        if ($aberta instanceof RedirectResponse) {
            return $aberta;
        }

        [$conta, $lida] = $aberta;

        return view('admin.google-ads.campanha', [
            'conta' => $conta,
            'campanha' => $lida,
            'plano' => $request->integer('plano') ?: null,
        ]);
    }

    public function mudarStatus(Request $request, string $campanha): RedirectResponse
    {
        $dados = $request->validate([
            'status' => ['required', Rule::in(GoogleAdsService::STATUS_ESCREVEVEIS)],
            'conta' => ['nullable', 'integer'],
            'dias' => ['nullable', 'integer'],
            'voltar' => ['nullable', 'string', 'max:20'],
            'plano' => ['nullable', 'integer'],
        ]);

        $conta = $this->conta($request, isset($dados['conta']) ? (int) $dados['conta'] : null);

        if ($conta === null) {
            return $this->volta($request, 'error', 'Nenhuma conta de Google Ads conectada a esta sessão — ou a conta pedida não está entre as suas.');
        }

        if ($conta->status !== GoogleAdsAccount::ATIVO) {
            return $this->volta($request, 'error', "A conta {$conta->nomeTela()} está desconectada: reconecte antes de mudar o status de uma campanha.");
        }

        $dias = (int) (($dados['dias'] ?? null) ?: config('google_ads.dias_padrao', 30));
        $campanha_tela = collect(GoogleAdsCache::metricas($conta, $dias)['campanhas'] ?? [])
            ->firstWhere('id', $campanha);

        // A campanha recém-criada pelo copiloto ainda não está na métrica sincronizada; a
        // leitura viva prova que ela existe nesta conta e dá o nome para o recado.
        if ($campanha_tela === null) {
            try {
                $lida = $this->editor->ler($conta, $campanha);
                $campanha_tela = ['id' => $campanha, 'nome' => $lida['nome'] ?? "Campanha #{$campanha}"];
            } catch (GoogleAdsFalhou $e) {
                return $this->volta($request, 'error', 'Esta campanha não está nesta conta do Google Ads: '.$e->motivoPublico);
            } catch (Throwable $e) {
                return $this->volta($request, 'error', 'Não foi possível ler a campanha no Google Ads: '.$e->getMessage());
            }
        }

        $rotulo = $this->servico->traduzStatus($dados['status']);

        try {
            // A prova antes da escrita: se o Google reprovar a operação simulada, nada acontece.
            $this->servico->validarOperacao($conta, $campanha, $dados['status']);
            $this->servico->mudarStatus($conta, $campanha, $dados['status']);
        } catch (Throwable $e) {
            if (GoogleAdsService::credencialRecusada($e)) {
                $conta->desconectar('O Google recusou a credencial durante uma escrita. É preciso reconectar a conta.');
            }

            return $this->volta($request, 'error', "O Google não aplicou a mudança em {$campanha_tela['nome']}: ".$e->getMessage());
        }

        Activity::log(
            'google_ads_campanha_status',
            "Campanha {$campanha_tela['nome']} ({$campanha}) passou para {$rotulo} na conta {$conta->numeroConta()}.",
            $conta,
            ['campaign_id' => $campanha, 'status' => $dados['status']],
        );

        if ($request->filled('plano')) {
            $planoModel = GoogleAdsPlano::find((int) $request->input('plano'));
            if ($planoModel) {
                $aplicacao = (array) ($planoModel->aplicacao_json ?? []);
                $aplicacao['status'] = $dados['status'];
                $aplicacao['status_rotulo'] = $rotulo;
                $planoModel->forceFill(['aplicacao_json' => $aplicacao])->save();
            }
        }

        // A linha velha da tabela é a da conta do Google antes da mudança: descarta a janela e
        // pede de novo, para a tela mostrar o status que o Google aceita agora.
        GoogleAdsCache::esquecerJanela($conta, $dias);
        SyncGoogleAdsMetrics::dispatch($conta->id, $dias);

        $recado = $dados['status'] === 'ENABLED'
            ? "{$campanha_tela['nome']} agora está {$rotulo} e começa a veicular. A métrica está sendo atualizada."
            : "{$campanha_tela['nome']} agora está {$rotulo}. A métrica está sendo atualizada.";

        return $this->volta($request, 'success', $recado);
    }

    public function editar(Request $request, string $campanha): View|RedirectResponse
    {
        $aberta = $this->abrir($request, $campanha);

        if ($aberta instanceof RedirectResponse) {
            return $aberta;
        }

        [$conta, $lida] = $aberta;

        return view('admin.google-ads.campanha-editar', ['conta' => $conta, 'campanha' => $lida]);
    }

    public function atualizar(Request $request, string $campanha): RedirectResponse
    {
        $aberta = $this->abrir($request, $campanha);

        if ($aberta instanceof RedirectResponse) {
            return $aberta;
        }

        [$conta, $lida] = $aberta;

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:128'],
            'orcamento' => ['required', 'numeric', 'min:1', 'max:5000'],
            'regioes' => ['required', 'string', 'max:500'],
            'negativas' => ['nullable', 'string', 'max:6000'],
        ]);

        $regioes = PlanoDeCampanha::lista(str_replace(',', "\n", $dados['regioes']));

        if ($regioes === []) {
            return back()->withInput()->withErrors(['regioes' => 'Escreva ao menos uma região, como aparece no Google Ads (ex.: São Paulo).']);
        }

        try {
            $resultado = $this->editor->atualizarCampanha($conta, $lida, [
                'nome' => $dados['nome'],
                'orcamento_diario_centavos' => (int) round(((float) $dados['orcamento']) * 100),
                'regioes' => $regioes,
                'negativas' => PlanoDeCampanha::lista($dados['negativas'] ?? ''),
            ]);
        } catch (Throwable $e) {
            return $this->erro($e, 'atualizar a campanha');
        }

        Activity::log(
            'google_ads_campanha_editada',
            "Campanha {$lida['nome']} ({$lida['id']}) editada na conta {$conta->numeroConta()}.",
            $conta,
            ['campaign_id' => $lida['id']] + $resultado,
        );

        $this->esquecerMetricas($conta);

        $mudou = $resultado['nome'] || $resultado['orcamento']
            || $resultado['regioes_criadas'] || $resultado['regioes_removidas']
            || $resultado['negativas_criadas'] || $resultado['negativas_removidas'];

        return redirect()
            ->route('admin.google-ads.campanhas.show', ['campanha' => $lida['id'], 'conta' => $conta->id])
            ->with($mudou ? 'success' : 'info', $mudou ? 'Campanha atualizada no Google Ads.' : 'Nada mudou: a campanha já estava assim no Google Ads.');
    }

    public function novoGrupo(Request $request, string $campanha): View|RedirectResponse
    {
        $aberta = $this->abrir($request, $campanha);

        if ($aberta instanceof RedirectResponse) {
            return $aberta;
        }

        [$conta, $lida] = $aberta;
        $modelo = $lida['grupos'][0]['anuncios'][0] ?? null;

        return view('admin.google-ads.campanha-grupo', [
            'conta' => $conta,
            'campanha' => $lida,
            'grupo' => null,
            // O anúncio do primeiro grupo serve de ponto de partida: a URL e as descrições
            // costumam ser as mesmas da campanha inteira.
            'anuncio' => $modelo,
            'cpc' => $lida['grupos'][0]['cpc_maximo_centavos'] ?? 200,
        ]);
    }

    public function criarGrupo(Request $request, string $campanha): RedirectResponse
    {
        $aberta = $this->abrir($request, $campanha);

        if ($aberta instanceof RedirectResponse) {
            return $aberta;
        }

        [$conta, $lida] = $aberta;
        $dados = $this->grupoDoFormulario($request);

        if ($dados instanceof RedirectResponse) {
            return $dados;
        }

        try {
            $resultado = $this->editor->criarGrupo($conta, $lida, $dados);
        } catch (Throwable $e) {
            return $this->erro($e, 'criar o grupo');
        }

        Activity::log(
            'google_ads_grupo_criado',
            "Grupo \"{$dados['nome']}\" ({$resultado['grupo_id']}) criado na campanha {$lida['nome']} da conta {$conta->numeroConta()}.",
            $conta,
            ['campaign_id' => $lida['id'], 'ad_group_id' => $resultado['grupo_id'], 'palavras' => $resultado['palavras']],
        );

        return redirect()
            ->route('admin.google-ads.campanhas.show', ['campanha' => $lida['id'], 'conta' => $conta->id])
            ->with('success', 'Grupo "'.$dados['nome'].'" criado no Google Ads com '.$resultado['palavras'].' palavra(s)-chave e o anúncio.');
    }

    public function editarGrupo(Request $request, string $campanha, string $grupo): View|RedirectResponse
    {
        $aberta = $this->abrir($request, $campanha);

        if ($aberta instanceof RedirectResponse) {
            return $aberta;
        }

        [$conta, $lida] = $aberta;
        $lido = $this->grupo($lida, $grupo);

        return view('admin.google-ads.campanha-grupo', [
            'conta' => $conta,
            'campanha' => $lida,
            'grupo' => $lido,
            'anuncio' => $lido['anuncios'][0] ?? null,
            'cpc' => $lido['cpc_maximo_centavos'],
        ]);
    }

    public function atualizarGrupo(Request $request, string $campanha, string $grupo): RedirectResponse
    {
        $aberta = $this->abrir($request, $campanha);

        if ($aberta instanceof RedirectResponse) {
            return $aberta;
        }

        [$conta, $lida] = $aberta;
        $lido = $this->grupo($lida, $grupo);
        $dados = $this->grupoDoFormulario($request);

        if ($dados instanceof RedirectResponse) {
            return $dados;
        }

        try {
            $resultado = $this->editor->atualizarGrupo($conta, $lido, $dados);
        } catch (Throwable $e) {
            return $this->erro($e, 'atualizar o grupo');
        }

        Activity::log(
            'google_ads_grupo_editado',
            "Grupo \"{$lido['nome']}\" ({$lido['id']}) editado na campanha {$lida['nome']} da conta {$conta->numeroConta()}.",
            $conta,
            ['campaign_id' => $lida['id'], 'ad_group_id' => $lido['id']] + $resultado,
        );

        $mudou = $resultado['grupo'] || $resultado['anuncio'] || $resultado['palavras_criadas'] || $resultado['palavras_removidas'];

        return redirect()
            ->route('admin.google-ads.campanhas.show', ['campanha' => $lida['id'], 'conta' => $conta->id])
            ->with($mudou ? 'success' : 'info', $mudou ? 'Grupo atualizado no Google Ads.' : 'Nada mudou: o grupo já estava assim no Google Ads.');
    }

    public function statusDoGrupo(Request $request, string $campanha, string $grupo): RedirectResponse
    {
        $aberta = $this->abrir($request, $campanha);

        if ($aberta instanceof RedirectResponse) {
            return $aberta;
        }

        [$conta, $lida] = $aberta;
        $lido = $this->grupo($lida, $grupo);
        $dados = $request->validate(['status' => ['required', Rule::in(['ENABLED', 'PAUSED'])]]);

        try {
            $this->editor->mudarStatusDoGrupo($conta, $lido, $dados['status']);
        } catch (Throwable $e) {
            return $this->erro($e, 'mudar o status do grupo');
        }

        $rotulo = $this->editor->rotulo($dados['status'], 'anuncio');

        Activity::log(
            'google_ads_grupo_status',
            "Grupo \"{$lido['nome']}\" ({$lido['id']}) passou para {$rotulo} na campanha {$lida['nome']}.",
            $conta,
            ['campaign_id' => $lida['id'], 'ad_group_id' => $lido['id'], 'status' => $dados['status']],
        );

        return $this->paraCampanha($conta, $lida, 'success', 'Grupo "'.$lido['nome'].'" agora está '.mb_strtolower($rotulo).'.');
    }

    public function removerGrupo(Request $request, string $campanha, string $grupo): RedirectResponse
    {
        $aberta = $this->abrir($request, $campanha);

        if ($aberta instanceof RedirectResponse) {
            return $aberta;
        }

        [$conta, $lida] = $aberta;
        $lido = $this->grupo($lida, $grupo);

        try {
            $this->editor->removerGrupo($conta, $lido);
        } catch (Throwable $e) {
            return $this->erro($e, 'remover o grupo');
        }

        Activity::log(
            'google_ads_grupo_removido',
            "Grupo \"{$lido['nome']}\" ({$lido['id']}) removido da campanha {$lida['nome']} na conta {$conta->numeroConta()}.",
            $conta,
            ['campaign_id' => $lida['id'], 'ad_group_id' => $lido['id']],
        );

        return $this->paraCampanha($conta, $lida, 'success', 'Grupo "'.$lido['nome'].'" removido do Google Ads.');
    }

    public function recursos(Request $request, string $campanha): View|RedirectResponse
    {
        $aberta = $this->abrir($request, $campanha);

        if ($aberta instanceof RedirectResponse) {
            return $aberta;
        }

        [$conta, $lida] = $aberta;

        return view('admin.google-ads.campanha-recursos', ['conta' => $conta, 'campanha' => $lida]);
    }

    public function vincularImagem(Request $request, string $campanha): RedirectResponse
    {
        $aberta = $this->abrir($request, $campanha);

        if ($aberta instanceof RedirectResponse) {
            return $aberta;
        }

        [$conta, $lida] = $aberta;
        $dados = $request->validate([
            'imagem' => ['required', 'string', 'max:255'],
            'nome' => ['nullable', 'string', 'max:128'],
        ]);

        if (! Envio::caminhoConfere($dados['imagem'], 'google_ads_imagem')) {
            return back()->withErrors(['imagem' => 'Envie a imagem pelo campo acima antes de vincular.']);
        }

        $nome = trim((string) ($dados['nome'] ?? '')) ?: pathinfo((string) $dados['imagem'], PATHINFO_FILENAME);

        try {
            $resultado = $this->editor->vincularImagem($conta, $lida, Storage::disk('public')->path($dados['imagem']), $nome);
        } catch (Throwable $e) {
            return $this->erro($e, 'vincular a imagem');
        }

        // O Google já guarda a imagem; a cópia local só ocuparia disco.
        Envio::remover($dados['imagem'], 'google_ads_imagem');

        Activity::log(
            'google_ads_recurso_vinculado',
            "Imagem \"{$nome}\" vinculada à campanha {$lida['nome']} na conta {$conta->numeroConta()}.",
            $conta,
            ['campaign_id' => $lida['id'], 'tipo' => 'AD_IMAGE', 'asset' => $resultado['asset']],
        );

        return $this->paraRecursos($conta, $lida, 'success', 'Imagem vinculada à campanha. O Google revisa o recurso antes de exibi-lo.');
    }

    public function vincularSitelink(Request $request, string $campanha): RedirectResponse
    {
        $aberta = $this->abrir($request, $campanha);

        if ($aberta instanceof RedirectResponse) {
            return $aberta;
        }

        [$conta, $lida] = $aberta;
        $dados = $request->validate([
            'texto' => ['required', 'string', 'max:25'],
            'url' => ['required', 'url', 'max:255'],
            'descricao1' => ['nullable', 'string', 'max:35'],
            'descricao2' => ['nullable', 'string', 'max:35', 'required_with:descricao1'],
        ], [
            'descricao2.required_with' => 'O Google pede as duas descrições juntas: preencha a segunda ou apague a primeira.',
        ]);

        try {
            $resultado = $this->editor->vincularSitelink($conta, $lida, $dados);
        } catch (Throwable $e) {
            return $this->erro($e, 'vincular o sitelink');
        }

        Activity::log(
            'google_ads_recurso_vinculado',
            "Sitelink \"{$dados['texto']}\" vinculado à campanha {$lida['nome']} na conta {$conta->numeroConta()}.",
            $conta,
            ['campaign_id' => $lida['id'], 'tipo' => 'SITELINK', 'asset' => $resultado['asset']],
        );

        return $this->paraRecursos($conta, $lida, 'success', 'Sitelink "'.$dados['texto'].'" vinculado à campanha.');
    }

    public function vincularFrase(Request $request, string $campanha): RedirectResponse
    {
        $aberta = $this->abrir($request, $campanha);

        if ($aberta instanceof RedirectResponse) {
            return $aberta;
        }

        [$conta, $lida] = $aberta;
        $dados = $request->validate(['texto' => ['required', 'string', 'max:25']]);

        try {
            $resultado = $this->editor->vincularFrase($conta, $lida, $dados['texto']);
        } catch (Throwable $e) {
            return $this->erro($e, 'vincular a frase de destaque');
        }

        Activity::log(
            'google_ads_recurso_vinculado',
            "Frase de destaque \"{$dados['texto']}\" vinculada à campanha {$lida['nome']} na conta {$conta->numeroConta()}.",
            $conta,
            ['campaign_id' => $lida['id'], 'tipo' => 'CALLOUT', 'asset' => $resultado['asset']],
        );

        return $this->paraRecursos($conta, $lida, 'success', 'Frase de destaque "'.$dados['texto'].'" vinculada à campanha.');
    }

    public function desvincularRecurso(Request $request, string $campanha): RedirectResponse
    {
        $aberta = $this->abrir($request, $campanha);

        if ($aberta instanceof RedirectResponse) {
            return $aberta;
        }

        [$conta, $lida] = $aberta;
        $dados = $request->validate(['vinculo' => ['required', 'string', 'max:255']]);

        $recurso = collect($lida['recursos'])->flatten(1)->firstWhere('vinculo', $dados['vinculo']);

        if ($recurso === null) {
            return $this->paraRecursos($conta, $lida, 'error', 'Este recurso não está vinculado a esta campanha. Recarregue a tela.');
        }

        try {
            $this->editor->desvincularRecurso($conta, $dados['vinculo']);
        } catch (Throwable $e) {
            return $this->erro($e, 'desvincular o recurso');
        }

        $nome = $recurso['texto'] ?? $recurso['nome'] ?? $recurso['asset_id'];

        Activity::log(
            'google_ads_recurso_desvinculado',
            "Recurso \"{$nome}\" desvinculado da campanha {$lida['nome']} na conta {$conta->numeroConta()}.",
            $conta,
            ['campaign_id' => $lida['id'], 'vinculo' => $dados['vinculo']],
        );

        return $this->paraRecursos($conta, $lida, 'success', 'Recurso "'.$nome.'" desvinculado da campanha. Ele continua na conta do Google Ads.');
    }

    /**
     * A conta e a leitura viva da campanha, ou o redirect com o motivo em português.
     *
     * @return array{0: GoogleAdsAccount, 1: array<string, mixed>}|RedirectResponse
     */
    private function abrir(Request $request, string $campanhaId): array|RedirectResponse
    {
        $conta = $this->conta($request, $request->filled('conta') ? (int) $request->input('conta') : null);

        if ($conta === null) {
            return redirect()->route('admin.google-ads.index')
                ->with('error', 'Nenhuma conta de Google Ads conectada a esta sessão — ou a conta pedida não está entre as suas.');
        }

        try {
            return [$conta, $this->editor->ler($conta, $campanhaId)];
        } catch (GoogleAdsFalhou $e) {
            return redirect()->route('admin.google-ads.index', ['conta' => $conta->id])->with('error', $e->motivoPublico);
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('admin.google-ads.index', ['conta' => $conta->id])
                ->with('error', 'Não consegui ler a campanha no Google Ads agora. Tente de novo em instantes.');
        }
    }

    /** O grupo pela leitura viva: um id que não está nela é 404, não uma escrita. */
    private function grupo(array $campanha, string $grupoId): array
    {
        $grupo = collect($campanha['grupos'])->firstWhere('id', $grupoId);

        abort_if($grupo === null, 404, 'Este grupo não está nesta campanha.');

        return $grupo;
    }

    /**
     * O formulário de grupo conferido com as travas do Google (config `google_ads.rsa`).
     *
     * @return array<string, mixed>|RedirectResponse
     */
    private function grupoDoFormulario(Request $request): array|RedirectResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'cpc_maximo' => ['required', 'numeric', 'min:0.10', 'max:50'],
            'palavras' => ['required', 'string', 'max:2500'],
            'url' => ['required', 'url', 'max:255'],
            'titulos' => ['required', 'string', 'max:600'],
            'descricoes' => ['required', 'string', 'max:500'],
            'caminhos' => ['nullable', 'string', 'max:60'],
        ]);

        $rsa = (array) config('google_ads.rsa');
        $titulos = PlanoDeCampanha::lista($dados['titulos']);
        $descricoes = PlanoDeCampanha::lista($dados['descricoes']);
        $caminhos = PlanoDeCampanha::lista(str_replace(['/', ','], "\n", (string) ($dados['caminhos'] ?? '')));
        $palavras = array_values(array_filter(
            array_map(fn (string $linha): array => PlanoDeCampanha::palavraDeLinha($linha), PlanoDeCampanha::lista($dados['palavras'])),
            static fn (array $p): bool => $p['texto'] !== '',
        ));
        $erros = [];

        if ($palavras === []) {
            $erros['palavras'] = 'Escreva ao menos uma palavra-chave, uma por linha.';
        }

        if (count($titulos) < $rsa['titulos_min'] || count($titulos) > $rsa['titulos_max']) {
            $erros['titulos'] = "O anúncio precisa de {$rsa['titulos_min']} a {$rsa['titulos_max']} títulos, um por linha.";
        } elseif (($longo = collect($titulos)->first(fn (string $t): bool => mb_strlen($t) > $rsa['caracteres_titulo'])) !== null) {
            $erros['titulos'] = "O título \"{$longo}\" passa de {$rsa['caracteres_titulo']} caracteres.";
        }

        if (count($descricoes) < $rsa['descricoes_min'] || count($descricoes) > $rsa['descricoes_max']) {
            $erros['descricoes'] = "O anúncio precisa de {$rsa['descricoes_min']} a {$rsa['descricoes_max']} descrições, uma por linha.";
        } elseif (($longa = collect($descricoes)->first(fn (string $d): bool => mb_strlen($d) > $rsa['caracteres_descricao'])) !== null) {
            $erros['descricoes'] = "A descrição \"{$longa}\" passa de {$rsa['caracteres_descricao']} caracteres.";
        }

        if (count($caminhos) > 2 || collect($caminhos)->contains(fn (string $c): bool => mb_strlen($c) > $rsa['caracteres_rota'] || ! preg_match('/^[\p{L}\p{N}\-]+$/u', $c))) {
            $erros['caminhos'] = "São até duas rotas de até {$rsa['caracteres_rota']} caracteres, só letras, números e hífen (ex.: rastreamento/frota).";
        }

        if ($erros !== []) {
            return back()->withInput()->withErrors($erros);
        }

        return [
            'nome' => trim($dados['nome']),
            'cpc_maximo_centavos' => (int) round(((float) $dados['cpc_maximo']) * 100),
            'palavras' => $palavras,
            'anuncio' => [
                'url' => $dados['url'],
                'titulos' => $titulos,
                'descricoes' => $descricoes,
                'caminhos' => $caminhos,
            ],
        ];
    }

    private function erro(Throwable $e, string $acao): RedirectResponse
    {
        if ($e instanceof GoogleAdsFalhou) {
            return back()->withInput()->with('error', $e->motivoPublico);
        }

        report($e);

        return back()->withInput()->with('error', 'Não consegui '.$acao.' no Google Ads agora. Tente de novo em instantes.');
    }

    private function paraCampanha(GoogleAdsAccount $conta, array $campanha, string $tipo, string $mensagem): RedirectResponse
    {
        $this->esquecerMetricas($conta);

        return redirect()
            ->route('admin.google-ads.campanhas.show', ['campanha' => $campanha['id'], 'conta' => $conta->id])
            ->with($tipo, $mensagem);
    }

    private function paraRecursos(GoogleAdsAccount $conta, array $campanha, string $tipo, string $mensagem): RedirectResponse
    {
        return redirect()
            ->route('admin.google-ads.campanhas.recursos', ['campanha' => $campanha['id'], 'conta' => $conta->id])
            ->with($tipo, $mensagem);
    }

    /** O nome e o status na tabela de métricas são os de antes da escrita: a janela é pedida de novo. */
    private function esquecerMetricas(GoogleAdsAccount $conta): void
    {
        $dias = (int) config('google_ads.dias_padrao', 30);
        GoogleAdsCache::esquecerJanela($conta, $dias);
        SyncGoogleAdsMetrics::dispatch($conta->id, $dias);
    }

    /**
     * A conta desta escrita, pela mesma régua de visibilidade da tela: o `?conta=` do formulário
     * só vale se estiver entre as que o usuário da sessão pode ver.
     */
    private function conta(Request $request, ?int $contaId): ?GoogleAdsAccount
    {
        $contas = GoogleAdsAccount::visiveisPara($request->user())->comCampanhas()->orderByDesc('id')->get();

        return $contaId === null ? $contas->first() : $contas->firstWhere('id', $contaId);
    }

    /**
     * Para onde o status volta: a ficha da campanha, a ficha do plano que a criou ou a lista
     * com os filtros no endereço — o formulário é POST, quem descreve a tela é a query.
     */
    private function volta(Request $request, string $tipo, string $mensagem): RedirectResponse
    {
        $voltar = (string) $request->input('voltar', '');

        if ($voltar === 'campanha') {
            return redirect()
                ->route('admin.google-ads.campanhas.show', array_filter(['campanha' => $request->route('campanha'), 'conta' => $request->input('conta')]))
                ->with($tipo, $mensagem);
        }

        if ($voltar === 'plano' && $request->filled('plano')) {
            return redirect()
                ->route('admin.google-ads.copiloto.show', (int) $request->input('plano'))
                ->with($tipo, $mensagem);
        }

        return redirect()
            ->route('admin.google-ads.index', array_filter($request->only(['dias', 'busca', 'situacao', 'conta']), fn ($valor): bool => $valor !== null && $valor !== ''))
            ->with($tipo, $mensagem);
    }
}

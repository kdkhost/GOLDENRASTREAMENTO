<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\GoogleAdsAccount;
use App\Models\GoogleAdsCopiloto;
use App\Models\GoogleAdsPlano;
use App\Services\Assistente\Provedores\Fabrica;
use App\Services\GoogleAds\Copiloto;
use App\Services\GoogleAds\Excecoes\CopilotoIndisponivel;
use App\Services\GoogleAds\Excecoes\GoogleAdsFalhou;
use App\Services\GoogleAds\GoogleAdsEditor;
use App\Services\GoogleAds\GoogleAdsService;
use App\Services\GoogleAds\GoogleAdsWriter;
use App\Services\GoogleAds\PlanoDeCampanha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * O copiloto de tráfego: a ficha dele, o briefing e os planos de campanha.
 *
 * A tela não é Livewire porque quase tudo aqui é formulário de escrita — e o painel já tem um
 * caminho só para isso: POST de verdade, respondido em segundo plano por `golden-salvar.js`,
 * com confirmação em SweetAlert2 para o que não volta atrás. Gerar um plano é uma chamada de
 * IA e demora; o botão fica ocupado enquanto isso, e o navegador vai para a ficha do plano
 * quando ela fica pronta.
 *
 * A ordem é a trava: a IA propõe, uma pessoa aprova, e só então a conta do Google é tocada —
 * e ainda assim a campanha nasce pausada. Nenhum botão daqui escreve no Google sem essa
 * sequência, e nenhum plano aplicado pode ser aplicado de novo.
 */
class GoogleAdsCopilotoController extends Controller
{
    public function __construct(
        private Copiloto $copiloto,
        private GoogleAdsWriter $escritor,
    ) {}

    /** A lista de planos, a ficha do copiloto e o que falta para ele atender. */
    public function index(Request $request): View
    {
        $ficha = GoogleAdsCopiloto::garanta();

        return view('admin.google-ads.copiloto', [
            'ficha' => $ficha,
            'planos' => GoogleAdsPlano::visiveisPara($request->user())->latest('id')->paginate(itens_por_pagina()),
            'contas' => GoogleAdsAccount::visiveisPara($request->user())->comCampanhas()->orderByDesc('id')->get(),
            'ia_pronta' => Fabrica::pronta('conversa'),
            'ia_modelo' => Fabrica::rodada('conversa'),
            'ia_faltando' => Fabrica::faltando(),
            'credenciais_faltando' => GoogleAdsService::credenciaisFaltantes(),
            'objetivos' => (array) config('google_ads.copiloto.objetivos'),
        ]);
    }

    /** A ficha é uma só e o painel é a fonte dela — como a ficha da Ana. */
    public function salvarFicha(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:60'],
            'cargo' => ['required', 'string', 'max:120'],
            'tom' => ['nullable', 'string', 'max:160'],
            'instrucoes' => ['nullable', 'string', 'max:4000'],
        ]);

        GoogleAdsCopiloto::gravar($dados);

        Activity::log(
            'copiloto_ads_ficha',
            'Ficha do copiloto de tráfego atualizada.',
            null,
            ['nome' => $dados['nome']],
        );

        return back()->with('success', 'Ficha do copiloto salva — as próximas gerações já usam estas instruções.');
    }

    /** O briefing vira plano: a IA escreve e `PlanoDeCampanha` decide o que passa. */
    public function gerar(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'conta' => ['nullable', 'integer'],
            'nome' => ['required', 'string', 'max:120'],
            'objetivo' => ['required', Rule::in(array_keys((array) config('google_ads.copiloto.objetivos')))],
            'orcamento' => ['required', 'numeric', 'min:1', 'max:5000'],
            'url' => ['required', 'string', 'max:255'],
            'regioes' => ['required', 'string', 'max:255'],
            'publico' => ['nullable', 'string', 'max:500'],
            'oferta' => ['nullable', 'string', 'max:500'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ]);

        $conta = $this->conta($request, isset($dados['conta']) ? (int) $dados['conta'] : null);

        $briefing = [
            'nome' => $dados['nome'],
            'objetivo' => $dados['objetivo'],
            'orcamento' => $dados['orcamento'],
            'url' => $dados['url'],
            'regioes' => $dados['regioes'],
            'publico' => $dados['publico'] ?? '',
            'oferta' => $dados['oferta'] ?? '',
            'observacoes' => $dados['observacoes'] ?? '',
        ];

        try {
            $resultado = $this->copiloto->planejar($briefing);
        } catch (CopilotoIndisponivel $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $plano = GoogleAdsPlano::create([
            'user_id' => $request->user()->id,
            'google_ads_account_id' => $conta?->id,
            'nome' => $resultado['plano']['nome'],
            'status' => GoogleAdsPlano::RASCUNHO,
            'objetivo' => $resultado['plano']['objetivo'],
            'orcamento_diario_centavos' => $resultado['plano']['orcamento_diario_centavos'],
            'briefing_json' => $briefing,
            'plano_json' => $resultado['plano'],
            'validacao_json' => [
                'problemas' => $resultado['problemas'],
                'avisos' => $resultado['avisos'],
                'forca' => $resultado['forca'],
            ],
            'modelo' => $resultado['modelo'],
        ]);

        Activity::log(
            'copiloto_ads_plano_gerado',
            "Plano de campanha \"{$plano->nome}\" gerado pelo copiloto.",
            $plano,
            ['problemas' => count($resultado['problemas']), 'forca' => $resultado['forca']['nota']],
        );

        $recado = $resultado['problemas'] === []
            ? 'Plano pronto. Confira a estrutura e aprove para poder aplicar no Google Ads.'
            : 'Plano gerado, mas há pendências que impedem aplicar — veja o que está em vermelho na ficha.';

        return redirect()
            ->route('admin.google-ads.copiloto.show', $plano)
            ->with($resultado['problemas'] === [] ? 'success' : 'warning', $recado);
    }

    /** A ficha do plano: o que a IA escreveu, o que a conferência apontou e o que fazer agora. */
    public function show(Request $request, GoogleAdsPlano $plano): View
    {
        $this->autoriza($request, $plano);

        $aplicacao = (array) ($plano->aplicacao_json ?? []);
        $campanhaCriada = (string) ($aplicacao['campanha_id'] ?? '');

        if ($campanhaCriada !== '' && $plano->conta && $plano->conta->status === GoogleAdsAccount::ATIVO) {
            try {
                $statusVivo = app(GoogleAdsEditor::class)->lerStatus($plano->conta, $campanhaCriada);
                if ($statusVivo !== null && ($aplicacao['status'] ?? null) !== $statusVivo) {
                    $aplicacao['status'] = $statusVivo;
                    $aplicacao['status_rotulo'] = app(GoogleAdsEditor::class)->rotulo($statusVivo);
                    $plano->forceFill(['aplicacao_json' => $aplicacao])->save();
                }
            } catch (Throwable) {
                // Preserva o estado local caso a leitura da API não responda
            }
        }

        return view('admin.google-ads.copiloto-show', [
            'plano' => $plano,
            'conteudo' => $plano->plano(),
            'problemas' => $plano->problemas(),
            'avisos' => $plano->avisos(),
            'forca' => (array) ($plano->validacao()['forca'] ?? []),
            'conta' => $plano->conta,
            'contas' => GoogleAdsAccount::visiveisPara($request->user())->comCampanhas()->orderByDesc('id')->get(),
            'credenciais_faltando' => GoogleAdsService::credenciaisFaltantes(),
            'aplicacao' => $aplicacao,
        ]);
    }

    /**
     * A tela onde o plano se escreve à mão: os mesmos campos do briefing, já preenchidos com o
     * que a IA devolveu, mais os grupos e o anúncio que ela montou.
     */
    public function editar(Request $request, GoogleAdsPlano $plano): View|RedirectResponse
    {
        $this->autoriza($request, $plano);

        if ($plano->status === GoogleAdsPlano::APLICADO) {
            return redirect()
                ->route('admin.google-ads.copiloto.show', $plano)
                ->with('error', 'Este plano já virou campanha no Google Ads. Faça uma cópia dele e edite a cópia.');
        }

        return view('admin.google-ads.copiloto-editar', [
            'plano' => $plano,
            'valores' => $this->formularioDe($plano),
            'objetivos' => (array) config('google_ads.copiloto.objetivos'),
            'problemas' => $plano->problemas(),
        ]);
    }

    /**
     * Gravar o plano digitado, conferido pela mesma régua que lê o que a IA devolve.
     *
     * O `briefing_json` não muda: ele é a prova do que foi pedido, e o que se edita aqui é o que
     * a IA escreveu. Salvando, o plano volta a rascunho de propósito — quem aprovou um plano com
     * outro orçamento não pode ter aprovado, sem querer, este aqui.
     */
    public function atualizar(Request $request, GoogleAdsPlano $plano): RedirectResponse
    {
        $this->autoriza($request, $plano);

        if ($plano->status === GoogleAdsPlano::APLICADO) {
            return back()->with('error', 'Este plano já virou campanha no Google Ads e não pode ser editado. Faça uma cópia dele e edite a cópia.');
        }

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'objetivo' => ['required', Rule::in(array_keys((array) config('google_ads.copiloto.objetivos')))],
            'orcamento' => ['required', 'numeric', 'min:1', 'max:5000'],
            'cpc_maximo' => ['nullable', 'numeric', 'min:0.10', 'max:50'],
            'url' => ['required', 'string', 'max:255'],
            'regioes' => ['required', 'string', 'max:255'],
            'grupos' => ['required', 'array', 'max:'.(int) config('google_ads.copiloto.grupos_max', 8)],
            'grupos.*.nome' => ['required', 'string', 'max:60'],
            'grupos.*.tema' => ['nullable', 'string', 'max:160'],
            'grupos.*.palavras' => ['required', 'string', 'max:2500'],
            'grupos.*.negativas' => ['nullable', 'string', 'max:6000'],
            'titulos' => ['required', 'string', 'max:600'],
            'descricoes' => ['required', 'string', 'max:500'],
            'caminhos' => ['nullable', 'string', 'max:60'],
            'justificativa' => ['nullable', 'string', 'max:2000'],
        ]);

        $conteudo = $plano->plano();

        $bruto = [
            'nome' => $dados['nome'],
            'objetivo' => $dados['objetivo'],
            'estrategia_lance' => $conteudo['estrategia_lance'] ?? 'MANUAL_CPC',
            'orcamento_diario' => $dados['orcamento'],
            'url' => $dados['url'],
            'regioes' => $dados['regioes'],
            'idiomas' => (array) ($conteudo['idiomas'] ?? []),
            'grupos' => $this->gruposDoFormulario((array) $dados['grupos']),
            'anuncios' => [
                'titulos' => $dados['titulos'],
                'descricoes' => $dados['descricoes'],
                'caminhos' => $dados['caminhos'] ?? '',
            ],
            'justificativa' => $dados['justificativa'] ?? '',
        ];

        if (filled($dados['cpc_maximo'] ?? null)) {
            $bruto['cpc_maximo_centavos'] = (int) round(((float) $dados['cpc_maximo']) * 100);
        }

        $conferido = PlanoDeCampanha::conferir($bruto, (array) $plano->briefing_json);

        $plano->forceFill([
            'nome' => $conferido['plano']['nome'],
            'objetivo' => $conferido['plano']['objetivo'],
            'orcamento_diario_centavos' => $conferido['plano']['orcamento_diario_centavos'],
            'plano_json' => $conferido['plano'],
            'validacao_json' => [
                'problemas' => $conferido['problemas'],
                'avisos' => $conferido['avisos'],
                'forca' => $conferido['forca'],
            ],
            'status' => GoogleAdsPlano::RASCUNHO,
            'erro' => null,
        ])->save();

        Activity::log(
            'copiloto_ads_plano_editado',
            "Plano \"{$plano->nome}\" editado na tela do copiloto.",
            $plano,
            ['problemas' => count($conferido['problemas'])],
        );

        return redirect()
            ->route('admin.google-ads.copiloto.show', $plano)
            ->with($conferido['problemas'] === [] ? 'success' : 'warning', $conferido['problemas'] === []
                ? 'Plano atualizado. Ele voltou a ser rascunho: precisa ser aprovado de novo antes de ir ao Google Ads.'
                : 'Plano atualizado, mas a conferência achou pendências — veja o que está em vermelho na ficha.');
    }

    /**
     * A cópia de um plano que já existe, para mudar algo sem perder a foto do que foi publicado.
     *
     * O plano aplicado é o registro do que o Google recebeu; editá-lo deixaria a ficha e a
     * campanha em mundos diferentes. A cópia nasce em rascunho com o mesmo briefing e cai direto
     * na tela de edição, que é para onde a pessoa queria ir.
     */
    public function duplicar(Request $request, GoogleAdsPlano $plano): RedirectResponse
    {
        $this->autoriza($request, $plano);

        $copia = GoogleAdsPlano::create([
            'user_id' => $request->user()->id,
            'google_ads_account_id' => $plano->google_ads_account_id,
            'nome' => $plano->nome,
            'status' => GoogleAdsPlano::RASCUNHO,
            'objetivo' => $plano->objetivo,
            'orcamento_diario_centavos' => $plano->orcamento_diario_centavos,
            'briefing_json' => $plano->briefing_json,
            'plano_json' => $plano->plano(),
            'validacao_json' => $plano->validacao(),
            'modelo' => $plano->modelo,
        ]);

        Activity::log('copiloto_ads_plano_duplicado', "Plano \"{$plano->nome}\" copiado como novo rascunho.", $copia);

        return redirect()
            ->route('admin.google-ads.copiloto.editar', $copia)
            ->with('success', 'Plano copiado. É da cópia, em rascunho, que você muda tudo — a campanha que já está no Google Ads continua igual.');
    }

    /** Aprovar é o ato humano que libera a escrita — não escreve nada por si só. */
    public function aprovar(Request $request, GoogleAdsPlano $plano): RedirectResponse
    {
        $this->autoriza($request, $plano);

        if ($plano->status !== GoogleAdsPlano::RASCUNHO) {
            return back()->with('error', $plano->status === GoogleAdsPlano::FALHOU
                ? 'Este plano já está aprovado — o que falhou foi a escrita no Google. Use "Tentar de novo".'
                : 'Só um plano em rascunho pode ser aprovado.');
        }

        if ($plano->problemas() !== []) {
            return back()->with('error', 'Este plano tem pendências que o Google recusaria. Corrija na tela de edição e salve de novo.');
        }

        $plano->forceFill(['status' => GoogleAdsPlano::APROVADO, 'erro' => null])->save();

        Activity::log('copiloto_ads_plano_aprovado', "Plano \"{$plano->nome}\" aprovado.", $plano);

        return back()->with('success', 'Plano aprovado. Agora ele pode ser aplicado no Google Ads — a campanha será criada pausada.');
    }

    /**
     * Aplicar é a única ação que cria campanha de verdade.
     *
     * A conta é escolhida aqui, não no plano, porque um plano pode ter sido escrito antes de
     * qualquer conexão existir. A campanha nasce pausada; ativá-la continua sendo um ato
     * separado, na tela de campanhas.
     */
    public function aplicar(Request $request, GoogleAdsPlano $plano): RedirectResponse
    {
        $this->autoriza($request, $plano);

        if ($plano->status === GoogleAdsPlano::APLICADO) {
            return back()->with('error', 'Este plano já foi aplicado no Google Ads.');
        }

        if ($plano->status === GoogleAdsPlano::RASCUNHO) {
            return back()->with('error', 'Este plano ainda é um rascunho. Aprove primeiro, depois aplique.');
        }

        if (! $plano->podeAplicar()) {
            return back()->with('error', 'Este plano está como '.$plano->rotuloStatus().'. Edite o plano para ele voltar a rascunho.');
        }

        if ($plano->problemas() !== []) {
            return back()->with('error', 'Este plano ainda tem pendências e o Google o recusaria. Corrija na tela de edição e aprove de novo.');
        }

        $dados = $request->validate(['conta' => ['required', 'integer']]);
        $conta = $this->conta($request, (int) $dados['conta']);

        if ($conta === null) {
            return back()->with('error', 'Escolha uma conta conectada para aplicar o plano.');
        }

        try {
            $resultado = $this->escritor->aplicar($conta, $plano->plano(), $plano->id);
        } catch (GoogleAdsFalhou $e) {
            $plano->forceFill([
                'status' => GoogleAdsPlano::FALHOU,
                'erro' => $e->motivoPublico,
                'google_ads_account_id' => $conta->id,
            ])->save();

            Activity::log(
                'copiloto_ads_plano_falhou',
                "Falha ao aplicar o plano \"{$plano->nome}\" na conta {$conta->numeroConta()}: {$e->motivoPublico}",
                $plano,
            );

            return back()->with('error', $e->motivoPublico);
        } catch (Throwable $e) {
            $plano->forceFill([
                'status' => GoogleAdsPlano::FALHOU,
                'erro' => 'A aplicação parou no meio. Confira a conta no Google Ads antes de gerar outro plano.',
                'google_ads_account_id' => $conta->id,
            ])->save();

            report($e);

            return back()->with('error', 'A aplicação parou no meio. Confira a conta no Google Ads antes de gerar outro plano.');
        }

        $plano->forceFill([
            'status' => GoogleAdsPlano::APLICADO,
            'google_ads_account_id' => $conta->id,
            'aplicacao_json' => $resultado,
            'aplicado_em' => now(),
            'erro' => null,
        ])->save();

        Activity::log(
            'copiloto_ads_plano_aplicado',
            "Plano \"{$plano->nome}\" aplicado na conta {$conta->numeroConta()}; campanha {$resultado['campanha_id']} criada pausada.",
            $plano,
            ['campanha_id' => $resultado['campanha_id']],
        );

        return back()->with(
            'success',
            'Campanha criada no Google Ads como pausada ('.$resultado['campanha_id'].'). '
            .'Revise no Google Ads e ative quando quiser começar a veicular.'
        );
    }

    /** Descartar tira o plano da lista sem apagar o registro do que foi pedido. */
    public function descartar(Request $request, GoogleAdsPlano $plano): RedirectResponse
    {
        $this->autoriza($request, $plano);

        if ($plano->status === GoogleAdsPlano::APLICADO) {
            return back()->with('error', 'Plano já aplicado não pode ser descartado.');
        }

        $plano->forceFill(['status' => GoogleAdsPlano::DESCARTADO])->save();

        Activity::log('copiloto_ads_plano_descartado', "Plano \"{$plano->nome}\" descartado.", $plano);

        return back()->with('success', 'Plano descartado. O briefing continua guardado na ficha dele.');
    }

    public function destroy(Request $request, GoogleAdsPlano $plano): RedirectResponse
    {
        $this->autoriza($request, $plano);

        $nome = $plano->nome;
        $plano->delete();

        Activity::log('copiloto_ads_plano_excluido', "Plano \"{$nome}\" excluído definitivamente.");

        return redirect()
            ->route('admin.google-ads.copiloto.index')
            ->with('success', 'Plano excluído.');
    }

    /**
     * O plano guardado em forma de formulário: cada lista vira texto de uma linha por item, que
     * é como a pessoa lê e reescreve uma lista no painel.
     */
    private function formularioDe(GoogleAdsPlano $plano): array
    {
        $conteudo = $plano->plano();

        $grupos = [];

        foreach ((array) ($conteudo['grupos'] ?? []) as $grupo) {
            $grupos[] = [
                'nome' => (string) ($grupo['nome'] ?? ''),
                'tema' => (string) ($grupo['tema'] ?? ''),
                'palavras' => implode("\n", array_map(
                    [PlanoDeCampanha::class, 'linhaDePalavra'],
                    (array) ($grupo['palavras_chave'] ?? [])
                )),
                'negativas' => implode("\n", (array) ($grupo['negativas'] ?? [])),
            ];
        }

        $anuncios = (array) ($conteudo['anuncios'] ?? []);

        return [
            'nome' => (string) ($conteudo['nome'] ?? $plano->nome),
            'objetivo' => (string) ($conteudo['objetivo'] ?? $plano->objetivo),
            'orcamento' => number_format(((int) ($conteudo['orcamento_diario_centavos'] ?? $plano->orcamento_diario_centavos)) / 100, 2, '.', ''),
            'cpc_maximo' => number_format(((int) ($conteudo['cpc_maximo_centavos'] ?? 0)) / 100, 2, '.', ''),
            'url' => (string) ($conteudo['url'] ?? ''),
            'regioes' => implode("\n", (array) ($conteudo['regioes'] ?? [])),
            'grupos' => $grupos,
            'titulos' => implode("\n", (array) ($anuncios['titulos'] ?? [])),
            'descricoes' => implode("\n", (array) ($anuncios['descricoes'] ?? [])),
            'caminhos' => implode("\n", (array) ($anuncios['caminhos'] ?? [])),
            'justificativa' => (string) ($conteudo['justificativa'] ?? ''),
        ];
    }

    /** O texto digitado de volta na forma que `PlanoDeCampanha::conferir()` lê. */
    private function gruposDoFormulario(array $grupos): array
    {
        $lista = [];

        foreach ($grupos as $grupo) {
            $lista[] = [
                'nome' => $grupo['nome'] ?? '',
                'tema' => $grupo['tema'] ?? '',
                // Uma linha por palavra, com a correspondência depois da barra vertical: é a única
                // forma de a pessoa trocar "ampla" por "exata" sem mexer no JSON do plano.
                'palavras_chave' => PlanoDeCampanha::lista($grupo['palavras'] ?? ''),
                'negativas' => $grupo['negativas'] ?? '',
            ];
        }

        return $lista;
    }

    /** A mesma régua da conta: o plano de outro só aparece para administrador. */
    private function autoriza(Request $request, GoogleAdsPlano $plano): void
    {
        $visivel = GoogleAdsPlano::visiveisPara($request->user())->whereKey($plano->id)->exists();

        abort_unless($visivel, 404);
    }

    private function conta(Request $request, ?int $contaId): ?GoogleAdsAccount
    {
        $contas = GoogleAdsAccount::visiveisPara($request->user())->comCampanhas()->orderByDesc('id')->get();

        return $contaId === null ? $contas->first() : $contas->firstWhere('id', $contaId);
    }
}

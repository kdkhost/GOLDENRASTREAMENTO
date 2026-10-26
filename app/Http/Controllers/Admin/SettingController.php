<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Setting;
use App\Support\ChavesDeIntegracao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        return view('admin.settings.index', [
            'settings' => Setting::all_values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_nome' => ['required', 'string', 'max:120'],
            'contato_whatsapp' => ['required', 'string', 'max:32'],
            'contato_telefone' => ['nullable', 'string', 'max:60'],
            'contato_email' => ['nullable', 'email', 'max:160'],
            'empresa_endereco' => ['nullable', 'string', 'max:255'],
            'empresa_cidade' => ['nullable', 'string', 'max:120'],
            'empresa_cep' => ['nullable', 'string', 'max:12'],
            'acesso_financeiro_url' => ['nullable', 'url', 'max:255'],
            'acesso_servidor_1_url' => ['nullable', 'url', 'max:255'],
            'acesso_servidor_2_url' => ['nullable', 'url', 'max:255'],
            'acesso_botao_rotulo' => ['nullable', 'string', 'max:40'],
            'acesso_conta_rotulo' => ['nullable', 'string', 'max:60'],
            'acesso_area_titulo' => ['nullable', 'string', 'max:60'],
            'acesso_portal_titulo' => ['nullable', 'string', 'max:60'],
            'acesso_portal_desc' => ['nullable', 'string', 'max:80'],
            'acesso_financeiro_titulo' => ['nullable', 'string', 'max:60'],
            'acesso_financeiro_desc' => ['nullable', 'string', 'max:80'],
            'acesso_modal_titulo' => ['nullable', 'string', 'max:80'],
            'acesso_modal_desc' => ['nullable', 'string', 'max:160'],
            'acesso_servidor_1_titulo' => ['nullable', 'string', 'max:60'],
            'acesso_servidor_2_titulo' => ['nullable', 'string', 'max:60'],
            'acesso_servidor_1_icone' => ['nullable', 'string', 'in:'.implode(',', array_keys((array) config('golden.acessos.icones')))],
            'acesso_servidor_2_icone' => ['nullable', 'string', 'in:'.implode(',', array_keys((array) config('golden.acessos.icones')))],
            'acesso_ajuda' => ['nullable', 'string', 'max:120'],
            'acesso_ajuda_link' => ['nullable', 'string', 'max:60'],
            'tracking_enabled' => ['nullable', 'boolean'],
            'ga_measurement_id' => ['nullable', 'string', 'max:60'],
            'meta_pixel_id' => ['nullable', 'string', 'max:60'],
            'gtm_id' => ['nullable', 'string', 'max:60'],
        ]);

        $data['contato_whatsapp'] = preg_replace('/\D/', '', $data['contato_whatsapp']);
        $data['tracking_enabled'] = $request->boolean('tracking_enabled') ? '1' : '0';

        foreach ($data as $chave => $valor) {
            Setting::set($chave, $valor);
        }

        Activity::log('configuracao_salva', 'Configurações do sistema atualizadas.');

        return back()->with('success', 'Configurações salvas.');
    }

    /**
     * Caixa multicanal de WhatsApp do site (botão flutuante). Cada canal pode apontar
     * para outro número; número vazio herda o principal da seção de contato.
     */
    /**
     * A caixa multicanal inteira, item por item: os textos do cabeçalho, o selo, a dica do
     * botão, o rodapé e a mensagem de abertura; e, em cada canal, nome, descrição, ícone,
     * número, mensagem pronta e o interruptor. Campo vazio apaga a linha do banco e devolve
     * aquele item ao padrão de `config('golden.whatsapp_box')` — nunca deixa buraco na tela.
     */
    public function whatsapp(Request $request): RedirectResponse
    {
        $icones = array_keys(config('golden.whatsapp_box.icones'));

        $regras = [
            'whatsapp_box_titulo' => ['nullable', 'string', 'max:120'],
            'whatsapp_box_subtitulo' => ['nullable', 'string', 'max:180'],
            'whatsapp_box_online' => ['nullable', 'string', 'max:60'],
            'whatsapp_box_flutuante' => ['nullable', 'string', 'max:60'],
            'whatsapp_box_rodape' => ['nullable', 'string', 'max:80'],
            'whatsapp_box_abertura' => ['nullable', 'string', 'max:300'],
        ];

        foreach (config('golden.whatsapp_box.canais') as $canal) {
            $chave = 'whatsapp_canal_'.$canal['id'];
            $regras[$chave.'_nome'] = ['nullable', 'string', 'max:60'];
            $regras[$chave.'_desc'] = ['nullable', 'string', 'max:120'];
            $regras[$chave.'_icone'] = ['nullable', Rule::in($icones)];
            $regras[$chave.'_numero'] = ['nullable', 'string', 'max:20'];
            $regras[$chave.'_msg'] = ['nullable', 'string', 'max:300'];
        }

        $data = $request->validate($regras);

        Setting::set('whatsapp_box_ativo', $request->boolean('whatsapp_box_ativo') ? '1' : '0');

        foreach (['titulo', 'subtitulo', 'online', 'flutuante', 'rodape', 'abertura'] as $campo) {
            Setting::set('whatsapp_box_'.$campo, trim((string) ($data['whatsapp_box_'.$campo] ?? '')));
        }

        foreach (config('golden.whatsapp_box.canais') as $canal) {
            $chave = 'whatsapp_canal_'.$canal['id'];
            Setting::set($chave.'_ativo', $request->boolean($chave.'_ativo') ? '1' : '0');
            Setting::set($chave.'_numero', preg_replace('/\D/', '', (string) ($data[$chave.'_numero'] ?? '')));
            Setting::set($chave.'_nome', trim((string) ($data[$chave.'_nome'] ?? '')));
            Setting::set($chave.'_desc', trim((string) ($data[$chave.'_desc'] ?? '')));
            Setting::set($chave.'_icone', (string) ($data[$chave.'_icone'] ?? ''));
            Setting::set($chave.'_msg', trim((string) ($data[$chave.'_msg'] ?? '')));
        }

        Activity::log('configuracao_salva', 'Caixa de WhatsApp do site atualizada.');

        return back()->with('success', 'Botão de WhatsApp atualizado.');
    }

    /**
     * As chaves de integração: o painel grava no banco e o banco passa a mandar.
     *
     * Três regras, todas de `ChavesDeIntegracao`, e a tela não inventa nenhuma:
     * campo vazio apaga a linha do banco (o `.env` volta a conduzir); segredo cifrado nunca
     * volta para o HTML; e um segredo deixado em branco sem o pedido explícito de apagar fica
     * como está — porque a caixa nasce vazia de propósito, e tratá-la como "limpar" apagaria
     * a chave do Google toda vez que alguém salvasse o SMTP.
     */
    public function chaves(Request $request): RedirectResponse
    {
        $grupo = $request->input('grupo');
        $definicao = ChavesDeIntegracao::grupos()[$grupo] ?? null;

        if ($definicao === null) {
            return back()->with('error', 'Grupo de chaves desconhecido.');
        }

        $campos = $definicao['campos'];
        $regras = [];
        $nomes = [];

        foreach ($campos as $campo) {
            // Toda chave admite vazio — é assim que se devolve um valor ao .env. O resto da regra
            // é de `ChavesDeIntegracao::regras()`, que conhece o tipo e a lista fechada de cada
            // campo: somar um `max:255` por cima transformaria "465" em string de 3 caracteres e
            // o validador acusaria um número que está certo.
            $regras[$campo['chave']] = array_merge(['nullable'], ChavesDeIntegracao::regras($campo));

            // O nome do campo na frase de erro é o rótulo da tela, não o nome da coluna do banco:
            // "assistente rodada conversa" é o que o validador inventaria sozinho, e ninguém
            // administra a máquina pelo nome de variável do config.
            $nomes[$campo['chave']] = $campo['label'];

            // Brasileiro escreve vírgula no megabyte. O validador da framework só conhece o ponto
            // (`is_numeric('6,5')` é falso), então a vírgula é trocada antes de validar — e o
            // `cast` float faz o resto na hora de gravar.
            if (($campo['cast'] ?? null) === 'float') {
                $decimal = str_replace(',', '.', (string) $request->input($campo['chave'], ''));

                // Vazio continua vazio — nulo, não string vazia, que é o que `nullable` aceita e
                // o que devolve o valor ao `.env`.
                $request->merge([$campo['chave'] => $decimal === '' ? null : $decimal]);
            }
        }

        $dados = $request->validate($regras, [], $nomes);
        $mexidas = 0;

        foreach ($campos as $campo) {
            $chave = $campo['chave'];
            $apagar = $request->boolean($chave.'_apagar');
            $valor = trim((string) ($dados[$chave] ?? ''));

            if ($valor === '' && ($campo['segredo'] ?? false) && ! $apagar) {
                continue;
            }

            if (ChavesDeIntegracao::guardar($chave, $apagar ? '' : $valor)) {
                $mexidas++;
            }
        }

        $rotulo = $definicao['rotulo'];
        Activity::log('chaves_integracao', "Chaves de integração de {$rotulo} atualizadas ({$mexidas} campo(s) com mudança).");

        return back()->with('success', $mexidas === 0
            ? "Nada mudou em {$rotulo}."
            : "{$rotulo}: {$mexidas} campo(s) atualizados. Vale a partir desta tela; o que ficou em branco voltou a obedecer ao .env.");
    }

    /** Liga/desliga a loja virtual imediatamente, sem recarregar o formulário. */
    public function loja(Request $request): RedirectResponse
    {
        $ativa = $request->boolean('ativa') ? '1' : '0';
        Setting::set('loja_ativa', $ativa);

        Activity::log('loja_toggle', 'Loja virtual '.($ativa === '1' ? 'ativada' : 'desativada').'.');

        return back()->with('success', $ativa === '1' ? 'Loja ativada.' : 'Loja desativada.');
    }

    /**
     * Regras comerciais da loja: frete (fixo, grátis acima de um valor ou liberado),
     * retirada na loja, chave Pix para o checkout e o e-mail que recebe alerta de venda.
     */
    public function regras(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'loja_frete' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'loja_frete_gratis_acima' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'loja_pix_chave' => ['nullable', 'string', 'max:120'],
            'loja_email_equipe' => ['nullable', 'email', 'max:160'],
        ]);

        Setting::set('loja_livre', $request->boolean('loja_livre') ? '1' : '0');
        Setting::set('loja_retirada', $request->boolean('loja_retirada') ? '1' : '0');
        Setting::set('loja_frete_cents', (int) round(((float) ($data['loja_frete'] ?? 0)) * 100));
        Setting::set('loja_frete_gratis_acima_cents', (int) round(((float) ($data['loja_frete_gratis_acima'] ?? 0)) * 100));
        Setting::set('loja_pix_chave', trim($data['loja_pix_chave'] ?? ''));
        Setting::set('loja_email_equipe', strtolower(trim($data['loja_email_equipe'] ?? '')));

        Activity::log('loja_regras', 'Regras comerciais da loja atualizadas.');

        return back()->with('success', 'Regras da loja salvas.');
    }
}

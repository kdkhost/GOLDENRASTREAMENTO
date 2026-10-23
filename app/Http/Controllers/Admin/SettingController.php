<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function whatsapp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'whatsapp_box_titulo' => ['nullable', 'string', 'max:120'],
            'whatsapp_box_subtitulo' => ['nullable', 'string', 'max:180'],
        ]);

        Setting::set('whatsapp_box_ativo', $request->boolean('whatsapp_box_ativo') ? '1' : '0');
        Setting::set('whatsapp_box_titulo', $data['whatsapp_box_titulo'] ?? '');
        Setting::set('whatsapp_box_subtitulo', $data['whatsapp_box_subtitulo'] ?? '');

        foreach (config('golden.whatsapp_box.canais') as $canal) {
            $chave = 'whatsapp_canal_' . $canal['id'];
            Setting::set($chave . '_ativo', $request->boolean($chave . '_ativo') ? '1' : '0');
            Setting::set($chave . '_numero', preg_replace('/\D/', '', (string) $request->input($chave . '_numero')));
        }

        Activity::log('configuracao_salva', 'Caixa de WhatsApp do site atualizada.');

        return back()->with('success', 'Botão de WhatsApp atualizado.');
    }

    /** Liga/desliga a loja virtual imediatamente, sem recarregar o formulário. */
    public function loja(Request $request): RedirectResponse
    {
        $ativa = $request->boolean('ativa') ? '1' : '0';
        Setting::set('loja_ativa', $ativa);

        // Os campos de frete só são aplicados quando vindos do formulário de configurações;
        // o botão rápido da sidebar envia apenas 'ativa' e não deve sobrescrever os demais.
        if ($request->has('loja_livre')) {
            Setting::set('loja_livre', $request->boolean('loja_livre') ? '1' : '0');
        }
        if ($request->filled('loja_frete')) {
            Setting::set('loja_frete_cents', (int) round(((float) $request->loja_frete) * 100));
        }

        Activity::log('loja_toggle', 'Loja virtual ' . ($ativa === '1' ? 'ativada' : 'desativada') . '.');

        return back()->with('success', $ativa === '1' ? 'Loja ativada.' : 'Loja desativada.');
    }
}

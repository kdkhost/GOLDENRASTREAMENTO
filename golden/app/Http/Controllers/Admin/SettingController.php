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
            'contato_email' => ['nullable', 'email', 'max:160'],
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
}

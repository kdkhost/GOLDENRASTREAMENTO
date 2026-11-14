<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Setting;
use App\Services\MaintenanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    public function __construct(
        protected MaintenanceService $maintenance
    ) {}

    public function index(Request $request): View
    {
        return view('admin.manutencao.index', [
            'ativa' => $this->maintenance->estaAtivo(),
            'modo' => $this->maintenance->modo(),
            'titulo' => $this->maintenance->titulo(),
            'subtitulo' => $this->maintenance->subtitulo(),
            'mensagem' => $this->maintenance->mensagem(),
            'previsaoFim' => $this->maintenance->previsaoFim()?->format('Y-m-d\TH:i'),
            'exibirCronometro' => $this->maintenance->exibirCronometro(),
            'exibirPlantao' => $this->maintenance->exibirPlantao(),
            'telefone' => $this->maintenance->telefone(),
            'email' => $this->maintenance->email(),
            'exibirAnimacao' => $this->maintenance->exibirAnimacao(),
            'velocidadeAnimacao' => $this->maintenance->velocidadeAnimacao(),
            'exibirSatelite' => $this->maintenance->exibirSatelite(),
            'exibirWhatsapp' => $this->maintenance->exibirWhatsapp(),
            'whatsappNumero' => $this->maintenance->whatsappNumero(),
            'whatsappMensagem' => $this->maintenance->whatsappMensagem(),
            'exibirRedes' => $this->maintenance->exibirRedes(),
            'instagram' => $this->maintenance->instagram(),
            'facebook' => $this->maintenance->facebook(),
            'youtube' => $this->maintenance->youtube(),
            'linkedin' => $this->maintenance->linkedin(),
            'tokenBypass' => $this->maintenance->tokenBypass(),
            'ipsPermitidos' => implode("\n", $this->maintenance->ipsPermitidos()),
            'meuIp' => $request->ip(),
            'urlBypass' => url('/?golden_bypass='.$this->maintenance->tokenBypass()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validados = $request->validate([
            'ativa' => ['nullable', 'boolean'],
            'modo' => ['required', 'in:total,publico_apenas'],
            'titulo' => ['required', 'string', 'max:160'],
            'subtitulo' => ['nullable', 'string', 'max:255'],
            'mensagem' => ['nullable', 'string', 'max:2000'],
            'previsao_fim' => ['nullable', 'string'],
            'exibir_cronometro' => ['nullable', 'boolean'],
            'exibir_plantao' => ['nullable', 'boolean'],
            'telefone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'string', 'email', 'max:120'],
            'exibir_animacao' => ['nullable', 'boolean'],
            'velocidade_animacao' => ['nullable', 'in:suave,normal,rapida'],
            'exibir_satelite' => ['nullable', 'boolean'],
            'exibir_whatsapp' => ['nullable', 'boolean'],
            'whatsapp_numero' => ['nullable', 'string', 'max:32'],
            'whatsapp_mensagem' => ['nullable', 'string', 'max:255'],
            'exibir_redes' => ['nullable', 'boolean'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'facebook' => ['nullable', 'string', 'max:255'],
            'youtube' => ['nullable', 'string', 'max:255'],
            'linkedin' => ['nullable', 'string', 'max:255'],
            'token_bypass' => ['nullable', 'string', 'max:64'],
            'ips_permitidos' => ['nullable', 'string'],
        ]);

        $this->maintenance->salvarConfiguracoes($validados);

        $statusRotulo = ! empty($validados['ativa']) ? 'LIGADO' : 'DESLIGADO';

        Activity::log(
            'maintenance_mode_update',
            "Atualizou configurações do Modo de Manutenção (Status: {$statusRotulo}).",
            $request->user(),
            ['ativa' => ! empty($validados['ativa'])]
        );

        return redirect()
            ->route('admin.manutencao.index')
            ->with('success', 'Configurações de manutenção salvas com sucesso!');
    }

    public function toggle(Request $request): RedirectResponse
    {
        $novo = $this->maintenance->alternarStatus();
        $rotulo = $novo ? 'ativado' : 'desativado';

        Activity::log(
            'maintenance_mode_toggle',
            "O Modo de Manutenção foi {$rotulo} por {$request->user()->name}.",
            $request->user(),
            ['novo_status' => $novo]
        );

        return redirect()
            ->route('admin.manutencao.index')
            ->with('success', "Modo de Manutenção {$rotulo} com sucesso!");
    }

    public function novoToken(Request $request): RedirectResponse
    {
        $token = Str::random(32);
        Setting::set('manutencao_token_bypass', $token);

        Activity::log(
            'maintenance_bypass_token_new',
            'Gerou nova chave de bypass para o Modo de Manutenção.',
            $request->user()
        );

        return redirect()
            ->route('admin.manutencao.index')
            ->with('success', 'Nova chave secreta de bypass gerada!');
    }
}

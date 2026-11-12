<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\SecurityEvent;
use App\Models\SecurityIpRule;
use App\Models\Setting;
use App\Services\Security\MilitaryShield;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MilitarySecurityController extends Controller
{
    public function __construct(
        protected MilitaryShield $shield
    ) {}

    public function index(Request $request): View
    {
        $estatisticas = $this->shield->estatisticas();

        // Regras de IP
        $blacklists = SecurityIpRule::blacklist()->latest()->paginate(15, ['*'], 'bl_page');
        $whitelists = SecurityIpRule::whitelist()->latest()->paginate(15, ['*'], 'wl_page');

        // Logs de eventos recentes com filtros
        $filtroEvento = $request->query('evento');
        $filtroNivel = $request->query('nivel');
        $busca = $request->query('busca');

        $queryLogs = SecurityEvent::latest('created_at');

        if ($filtroEvento) {
            $queryLogs->where('evento', $filtroEvento);
        }
        if ($filtroNivel) {
            $queryLogs->where('nivel', $filtroNivel);
        }
        if ($busca) {
            $queryLogs->where(function ($q) use ($busca): void {
                $q->where('ip', 'like', "%{$busca}%")
                    ->orWhere('url', 'like', "%{$busca}%")
                    ->orWhere('payload_suspeito', 'like', "%{$busca}%");
            });
        }

        $logs = $queryLogs->paginate(20, ['*'], 'logs_page')->withQueryString();

        return view('admin.seguranca-militar.index', [
            'shield' => $this->shield,
            'stats' => $estatisticas,
            'blacklists' => $blacklists,
            'whitelists' => $whitelists,
            'logs' => $logs,
            'meuIp' => $request->ip(),
            'filtroEvento' => $filtroEvento,
            'filtroNivel' => $filtroNivel,
            'busca' => $busca,
        ]);
    }

    public function configuracoes(Request $request): RedirectResponse
    {
        $validados = $request->validate([
            'shield_ativo' => ['nullable', 'boolean'],
            'shield_rigor' => ['required', 'in:monitoramento,padrao,alto,militar,lockdown'],
            'shield_bloquear_sqli' => ['nullable', 'boolean'],
            'shield_bloquear_xss' => ['nullable', 'boolean'],
            'shield_bloquear_rce' => ['nullable', 'boolean'],
            'shield_bloquear_scanners' => ['nullable', 'boolean'],
            'shield_bloquear_probes' => ['nullable', 'boolean'],
            'shield_autoban_ativo' => ['nullable', 'boolean'],
            'shield_autoban_limite' => ['required', 'integer', 'min:1', 'max:50'],
            'shield_autoban_horas' => ['required', 'integer', 'min:1', 'max:720'],
        ]);

        Setting::set('shield_ativo', ! empty($validados['shield_ativo']) ? '1' : '0');
        Setting::set('shield_rigor', $validados['shield_rigor']);
        Setting::set('shield_bloquear_sqli', ! empty($validados['shield_bloquear_sqli']) ? '1' : '0');
        Setting::set('shield_bloquear_xss', ! empty($validados['shield_bloquear_xss']) ? '1' : '0');
        Setting::set('shield_bloquear_rce', ! empty($validados['shield_bloquear_rce']) ? '1' : '0');
        Setting::set('shield_bloquear_scanners', ! empty($validados['shield_bloquear_scanners']) ? '1' : '0');
        Setting::set('shield_bloquear_probes', ! empty($validados['shield_bloquear_probes']) ? '1' : '0');
        Setting::set('shield_autoban_ativo', ! empty($validados['shield_autoban_ativo']) ? '1' : '0');
        Setting::set('shield_autoban_limite', (string) $validados['shield_autoban_limite']);
        Setting::set('shield_autoban_horas', (string) $validados['shield_autoban_horas']);

        Activity::log(
            'military_shield_config',
            "Atualizou as diretrizes do Escudo Militar (Rigor: {$validados['shield_rigor']}).",
            $request->user(),
            ['rigor' => $validados['shield_rigor']]
        );

        return redirect()
            ->route('admin.seguranca-militar.index', ['aba' => 'config'])
            ->with('success', 'Diretrizes do Escudo Militar atualizadas com sucesso!');
    }

    public function adicionarRegraIp(Request $request): RedirectResponse
    {
        $validados = $request->validate([
            'ip' => ['required', 'string', 'max:45'],
            'tipo' => ['required', 'in:whitelist,blacklist'],
            'motivo' => ['required', 'string', 'max:255'],
            'horas' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'nivel_ameaca' => ['nullable', 'in:baixo,medio,alto,critico'],
        ]);

        $ipLimpo = trim($validados['ip']);

        $this->shield->adicionarRegraIp(
            $ipLimpo,
            $validados['tipo'],
            $validados['motivo'],
            isset($validados['horas']) ? (int) $validados['horas'] : null,
            $validados['nivel_ameaca'] ?? SecurityIpRule::NIVEL_MEDIO,
            $request->user()->id
        );

        $tipoRotulo = $validados['tipo'] === 'whitelist' ? 'Lista Branca (Whitelist)' : 'Lista Negra (Blacklist)';

        Activity::log(
            'security_ip_rule_create',
            "Adicionou IP {$ipLimpo} à {$tipoRotulo}: {$validados['motivo']}",
            $request->user(),
            ['ip' => $ipLimpo, 'tipo' => $validados['tipo']]
        );

        return redirect()
            ->route('admin.seguranca-militar.index', ['aba' => 'ips'])
            ->with('success', "IP {$ipLimpo} adicionado à {$tipoRotulo}!");
    }

    public function removerRegraIp(Request $request, int $id): RedirectResponse
    {
        $regra = SecurityIpRule::findOrFail($id);
        $ip = $regra->ip;
        $tipo = $regra->tipo;

        $regra->delete();

        Activity::log(
            'security_ip_rule_delete',
            "Removeu a regra de IP {$ip} ({$tipo}).",
            $request->user(),
            ['ip' => $ip, 'tipo' => $tipo]
        );

        return redirect()
            ->route('admin.seguranca-militar.index', ['aba' => 'ips'])
            ->with('success', "Regra do IP {$ip} removida com sucesso!");
    }

    public function whitelistMeuIp(Request $request): RedirectResponse
    {
        $meuIp = (string) $request->ip();

        $this->shield->adicionarRegraIp(
            $meuIp,
            SecurityIpRule::TIPO_WHITELIST,
            'IP do Administrador '.$request->user()->name.' (liberação direta no painel)',
            null, // permanente
            SecurityIpRule::NIVEL_BAIXO,
            $request->user()->id
        );

        Activity::log(
            'security_whitelist_self',
            "Administrador {$request->user()->name} adicionou o próprio IP ({$meuIp}) à Lista Branca.",
            $request->user(),
            ['ip' => $meuIp]
        );

        return redirect()
            ->route('admin.seguranca-militar.index', ['aba' => 'ips'])
            ->with('success', "Seu IP ({$meuIp}) foi adicionado com sucesso à Lista Branca permanente!");
    }

    public function limparLogs(Request $request): RedirectResponse
    {
        $removidos = $this->shield->limparLogsAntigos(30);

        Activity::log(
            'security_logs_purge',
            "Expurgou {$removidos} logs de segurança com mais de 30 dias.",
            $request->user(),
            ['removidos' => $removidos]
        );

        return redirect()
            ->route('admin.seguranca-militar.index', ['aba' => 'logs'])
            ->with('success', "{$removidos} logs antigos foram limpos!");
    }
}


<?php

namespace App\Services\Security;

use App\Models\SecurityEvent;
use App\Models\SecurityIpRule;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class MilitaryShield
{
    public const RIGOR_MONITORAMENTO = 'monitoramento';

    public const RIGOR_PADRAO = 'padrao';

    public const RIGOR_ALTO = 'alto';

    public const RIGOR_MILITAR = 'militar';

    public const RIGOR_LOCKDOWN = 'lockdown';

    /**
     * Verifica se o Escudo Militar está ativo.
     */
    public function estaAtivo(): bool
    {
        return (string) Setting::get('shield_ativo', '1') === '1';
    }

    /**
     * Nível de rigor da proteção militar.
     */
    public function nivelRigor(): string
    {
        return (string) Setting::get('shield_rigor', self::RIGOR_MILITAR);
    }

    public function bloquearSqli(): bool
    {
        return (string) Setting::get('shield_bloquear_sqli', '1') === '1';
    }

    public function bloquearXss(): bool
    {
        return (string) Setting::get('shield_bloquear_xss', '1') === '1';
    }

    public function bloquearRce(): bool
    {
        return (string) Setting::get('shield_bloquear_rce', '1') === '1';
    }

    public function bloquearScanners(): bool
    {
        return (string) Setting::get('shield_bloquear_scanners', '1') === '1';
    }

    public function bloquearProbes(): bool
    {
        return (string) Setting::get('shield_bloquear_probes', '1') === '1';
    }

    public function autoBanAtivo(): bool
    {
        return (string) Setting::get('shield_autoban_ativo', '1') === '1';
    }

    public function limiteAutoBan(): int
    {
        return (int) Setting::get('shield_autoban_limite', 3);
    }

    public function horasAutoBan(): int
    {
        return (int) Setting::get('shield_autoban_horas', 24);
    }

    /**
     * Inspeciona uma requisição e retorna os detalhes da ameaça ou null se limpa.
     *
     * @return array<string, mixed>|null
     */
    public function inspecionar(Request $request): ?array
    {
        if (! $this->estaAtivo()) {
            return null;
        }

        $ip = (string) $request->ip();

        // 1. Checa se o IP está explicitamente na Whitelist
        if ($this->ipEstaNaWhitelist($ip)) {
            return null;
        }

        // Administradores e operadores autenticados nunca são bloqueados pelo WAF
        $user = $this->usuarioAutenticado($request);
        if ($user && in_array($user->role, ['admin', 'vendedor'], true)) {
            return null;
        }

        // 2. Checa se o sistema está em modo LOCKDOWN total (apenas Whitelist entra)
        if ($this->nivelRigor() === self::RIGOR_LOCKDOWN) {
            return [
                'evento' => 'lockdown',
                'nivel' => SecurityIpRule::NIVEL_CRITICO,
                'motivo' => 'Sistema em quarentena total (Lockdown Militar)',
                'payload' => null,
            ];
        }

        // 3. Checa se o IP já está na Blacklist ativa
        $regraBlacklist = $this->regraBlacklistAtiva($ip);
        if ($regraBlacklist !== null) {
            $regraBlacklist->increment('tentativas_bloqueadas');
            $regraBlacklist->update(['ultimo_bloqueio_em' => now()]);

            return [
                'evento' => 'blacklisted_access',
                'nivel' => $regraBlacklist->nivel_ameaca,
                'motivo' => 'Endereço IP listado na Lista Negra',
                'payload' => null,
                'ja_banido' => true,
            ];
        }

        $path = rawurldecode($request->path());
        $fullUrl = rawurldecode($request->fullUrl());
        $userAgent = (string) $request->userAgent();

        // 4. Detecção de Probes a arquivos e diretórios críticos
        if ($this->bloquearProbes()) {
            $probe = $this->detectarProbeSensivel($path, $fullUrl);
            if ($probe !== null) {
                return [
                    'evento' => 'sensitive_probe',
                    'nivel' => SecurityIpRule::NIVEL_CRITICO,
                    'motivo' => 'Tentativa de varredura de arquivo protegido ('.$probe.')',
                    'payload' => $path,
                ];
            }
        }

        // 5. Detecção de Scanners e Ferramentas Hostis via User-Agent
        if ($this->bloquearScanners() && $userAgent !== '') {
            $scanner = $this->detectarScanner($userAgent);
            if ($scanner !== null) {
                return [
                    'evento' => 'scanner',
                    'nivel' => SecurityIpRule::NIVEL_ALTO,
                    'motivo' => 'Scanner ou ferramenta automatizada hostil ('.$scanner.')',
                    'payload' => $userAgent,
                ];
            }
        }

        // 6. Detecção de Cabeçalhos Maliciosos (Log4j, Shellshock, etc.)
        $cabecalho = $this->detectarCabecalhoSuspeito($request);
        if ($cabecalho !== null) {
            return [
                'evento' => 'rce',
                'nivel' => SecurityIpRule::NIVEL_CRITICO,
                'motivo' => 'Cabeçalho HTTP hostil interceptado ('.$cabecalho.')',
                'payload' => substr($request->header($cabecalho) ?? '', 0, 200),
            ];
        }

        // 7. Inspeção de Parâmetros (Query String, POST inputs)
        $dadosParaInspecionar = $this->extrairValoresRequisicao($request);

        foreach ($dadosParaInspecionar as $chave => $valor) {
            if (! is_string($valor) || strlen($valor) < 3) {
                continue;
            }

            // Path Traversal / LFI
            if ($this->bloquearRce()) {
                $pt = $this->detectarPathTraversal($valor);
                if ($pt !== null) {
                    return [
                        'evento' => 'path_traversal',
                        'nivel' => SecurityIpRule::NIVEL_ALTO,
                        'motivo' => 'Tentativa de quebra de diretório (Path Traversal: '.$pt.')',
                        'payload' => "{$chave}={$valor}",
                    ];
                }

                $rce = $this->detectarRce($valor);
                if ($rce !== null) {
                    return [
                        'evento' => 'rce',
                        'nivel' => SecurityIpRule::NIVEL_CRITICO,
                        'motivo' => 'Tentativa de execução remota de código (RCE: '.$rce.')',
                        'payload' => "{$chave}={$valor}",
                    ];
                }
            }

            // SQL Injection
            if ($this->bloquearSqli()) {
                $sqli = $this->detectarSqli($valor);
                if ($sqli !== null) {
                    return [
                        'evento' => 'sqli',
                        'nivel' => SecurityIpRule::NIVEL_CRITICO,
                        'motivo' => 'Padrão de injeção SQL interceptado ('.$sqli.')',
                        'payload' => "{$chave}={$valor}",
                    ];
                }
            }

            // XSS Injection
            if ($this->bloquearXss()) {
                $xss = $this->detectarXss($valor);
                if ($xss !== null) {
                    return [
                        'evento' => 'xss',
                        'nivel' => SecurityIpRule::NIVEL_ALTO,
                        'motivo' => 'Padrão de script malicioso XSS interceptado ('.$xss.')',
                        'payload' => "{$chave}={$valor}",
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Registra o evento de bloqueio e aplica Auto-Ban caso necessário.
     *
     * @param  array<string, mixed>  $ameaca
     */
    public function registrarBloqueio(Request $request, array $ameaca): void
    {
        $ip = (string) $request->ip();
        $acao = $this->nivelRigor() === self::RIGOR_MONITORAMENTO ? 'registrado' : 'bloqueado_403';

        // Sanitização de payload para histórico seguro
        $payload = isset($ameaca['payload']) ? substr(strip_tags((string) $ameaca['payload']), 0, 500) : null;

        try {
            SecurityEvent::create([
                'ip' => $ip,
                'evento' => (string) ($ameaca['evento'] ?? 'ameaca_generica'),
                'nivel' => (string) ($ameaca['nivel'] ?? SecurityIpRule::NIVEL_MEDIO),
                'metodo' => $request->method(),
                'url' => substr($request->fullUrl(), 0, 1000),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'payload_suspeito' => $payload,
                'acao_tomada' => $acao,
                'user_id' => $request->user()?->id,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Não quebra a requisição se o log falhar
        }

        // Se o IP já estava na blacklist, não duplica e nem dispara novo auto-ban em loop
        if (! empty($ameaca['ja_banido'])) {
            return;
        }

        // Lógica de Prisão Automática (Auto-Ban)
        if ($this->autoBanAtivo() && $this->nivelRigor() !== self::RIGOR_MONITORAMENTO) {
            $this->processarAutoBan($ip, $ameaca);
        }
    }

    /**
     * Aplica o Auto-Ban baseado em severidade e histórico.
     *
     * @param  array<string, mixed>  $ameaca
     */
    protected function processarAutoBan(string $ip, array $ameaca): void
    {
        // Ataques críticos (SQLi, RCE, Probes de .env) recebem ban imediato
        $ehCritico = ($ameaca['nivel'] ?? '') === SecurityIpRule::NIVEL_CRITICO;

        $chaveCache = "shield_strikes_{$ip}";
        $infracoes = (int) Cache::get($chaveCache, 0) + 1;
        Cache::put($chaveCache, $infracoes, now()->addHours(6));

        if ($ehCritico || $infracoes >= $this->limiteAutoBan()) {
            $horas = $ehCritico ? ($this->horasAutoBan() * 2) : $this->horasAutoBan();
            $motivoBase = (string) ($ameaca['motivo'] ?? 'Ameaça interceptada');
            $motivoBase = (string) preg_replace('/^(Auto-Ban Militar:\s*|Endereço IP listado na Lista Negra:\s*)+/i', '', $motivoBase);
            $motivoBase = substr(trim($motivoBase), 0, 140);
            $motivo = "Auto-Ban Militar: {$motivoBase} ({$infracoes} infração(ões))";

            $this->adicionarRegraIp(
                $ip,
                SecurityIpRule::TIPO_BLACKLIST,
                $motivo,
                $horas,
                $ehCritico ? SecurityIpRule::NIVEL_CRITICO : SecurityIpRule::NIVEL_ALTO
            );
        }
    }

    public function ipEstaNaWhitelist(string $ip): bool
    {
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }

        return SecurityIpRule::where('ip', $ip)
            ->where('tipo', SecurityIpRule::TIPO_WHITELIST)
            ->ativas()
            ->exists();
    }

    public function regraBlacklistAtiva(string $ip): ?SecurityIpRule
    {
        return SecurityIpRule::where('ip', $ip)
            ->where('tipo', SecurityIpRule::TIPO_BLACKLIST)
            ->ativas()
            ->first();
    }

    public function adicionarRegraIp(
        string $ip,
        string $tipo,
        string $motivo,
        ?int $horas = null,
        string $nivel = SecurityIpRule::NIVEL_MEDIO,
        ?int $userId = null
    ): SecurityIpRule {
        $expiraEm = $horas !== null && $horas > 0 ? Carbon::now()->addHours($horas) : null;

        return SecurityIpRule::updateOrCreate(
            ['ip' => trim($ip), 'tipo' => $tipo],
            [
                'motivo' => substr(trim($motivo), 0, 250),
                'nivel_ameaca' => $nivel,
                'expira_em' => $expiraEm,
                'created_by_user_id' => $userId,
            ]
        );
    }

    public function removerRegraIp(int $id): bool
    {
        return (bool) SecurityIpRule::where('id', $id)->delete();
    }

    /**
     * Heurística de Injeção SQL.
     */
    public function detectarSqli(string $v): ?string
    {
        $padroes = [
            '/\bunion\s+(all\s+)?select\b/i' => 'UNION SELECT',
            '/\bselect\b.+\bfrom\b.+\binformation_schema\b/i' => 'INFORMATION_SCHEMA',
            '/\b(select|insert|update|delete|drop|alter|truncate)\s+[a-zA-Z0-9_\*]+\s+from\b/i' => 'Manipulação direta SQL',
            '/(\%27|\')\s*(or|and)\s+(\%27|\')?\d+(\%27|\')?\s*=\s*(\%27|\')?\d+/i' => 'OR/AND 1=1 tautologia',
            '/\bor\s+true\b|\band\s+true\b/i' => 'Booleano forçado',
            '/\b(benchmark|sleep)\s*\(\s*\d+\s*\)/i' => 'Time-based blind SQLi',
            '/\bwaitfor\s+delay\b/i' => 'WAITFOR DELAY',
            '/;\s*(drop|shutdown|exec|execute|declare)\b/i' => 'Multiplas declarações perigosas',
            '/0x[0-9a-fA-F]{8,}/' => 'Hexadecimal SQL payload',
        ];

        foreach ($padroes as $regex => $nome) {
            if (preg_match($regex, $v)) {
                return $nome;
            }
        }

        return null;
    }

    /**
     * Heurística de Cross-Site Scripting (XSS).
     */
    public function detectarXss(string $v): ?string
    {
        $padroes = [
            '/<\s*script\b[^>]*>/i' => 'Tag <script>',
            '/javascript\s*:/i' => 'URI javascript:',
            '/vbscript\s*:/i' => 'URI vbscript:',
            '/data\s*:\s*text\/html/i' => 'Data URI text/html',
            '/on(load|error|click|mouseover|mouseenter|focus|blur|submit)\s*=/i' => 'Manipulador de evento inline (onerror/onload)',
            '/<\s*(iframe|embed|object|svg)\b[^>]*>/i' => 'Elemento incorporado não autorizado',
            '/document\.(cookie|location|write)/i' => 'Manipulação de document/cookie',
        ];

        foreach ($padroes as $regex => $nome) {
            if (preg_match($regex, $v)) {
                return $nome;
            }
        }

        return null;
    }

    /**
     * Heurística de Remote Code Execution (RCE).
     */
    public function detectarRce(string $v): ?string
    {
        $padroes = [
            '/\b(system|exec|passthru|shell_exec|popen|proc_open)\s*\(/i' => 'Execução nativa de shell PHP',
            '/\bbase64_decode\s*\(/i' => 'Decodificação oculta de payload base64',
            '/\b(curl|wget)\s+http/i' => 'Comando de download de artefato malicioso',
            '/\b(powershell|cmd\.exe|bash|sh|zsh)\b/i' => 'Invocação direta de terminal',
        ];

        foreach ($padroes as $regex => $nome) {
            if (preg_match($regex, $v)) {
                return $nome;
            }
        }

        return null;
    }

    /**
     * Heurística de Path Traversal / LFI.
     */
    public function detectarPathTraversal(string $v): ?string
    {
        $padroes = [
            '/\.\.[\/\\\\]/' => 'Sequência ../ ou ..\\',
            '/\/etc\/(passwd|shadow|hosts)/i' => 'Acesso a arquivos Unix do sistema',
            '/\/proc\/self\//i' => 'Acesso a /proc/self/',
            '/[a-zA-Z]:\\\\windows\\\\/i' => 'Caminho absoluto do Windows',
            '/\bwin\.ini\b/i' => 'Arquivo win.ini',
        ];

        foreach ($padroes as $regex => $nome) {
            if (preg_match($regex, $v)) {
                return $nome;
            }
        }

        return null;
    }

    /**
     * Identifica ferramentas e scanners hostis conhecidos.
     */
    public function detectarScanner(string $ua): ?string
    {
        $scanners = [
            'sqlmap' => 'SQLMap',
            'nikto' => 'Nikto Web Scanner',
            'acunetix' => 'Acunetix Scanner',
            'dirbuster' => 'DirBuster',
            'gobuster' => 'GoBuster',
            'wpscan' => 'WPScan',
            'nmap' => 'Nmap NSE',
            'masscan' => 'Masscan',
            'hydra' => 'Hydra Brute Force',
            'burpsuite' => 'Burp Suite',
            'zgrab' => 'ZGrab',
            'censys' => 'Censys Scanner',
            'shodan' => 'Shodan Bot',
            'havij' => 'Havij SQL Tool',
        ];

        $uaLower = strtolower($ua);
        foreach ($scanners as $chave => $nome) {
            if (str_contains($uaLower, $chave)) {
                return $nome;
            }
        }

        return null;
    }

    /**
     * Identifica probes a arquivos altamente confidenciais.
     */
    public function detectarProbeSensivel(string $path, string $fullUrl): ?string
    {
        $alvos = [
            '.env',
            '.git',
            '.svn',
            'wp-config',
            'wp-admin',
            'wp-login.php',
            'xmlrpc.php',
            'phpmyadmin',
            'pma',
            'adminer',
            '.aws/credentials',
            'composer.lock',
            'config.json',
            '.bash_history',
            'storage/logs/',
            '.DS_Store',
            'web.config',
            'backup.sql',
            'dump.sql',
        ];

        $caminhoLower = strtolower($path);
        foreach ($alvos as $alvo) {
            if (str_contains($caminhoLower, $alvo)) {
                return $alvo;
            }
        }

        return null;
    }

    /**
     * Identifica cabeçalhos com payloads de exploits conhecidos.
     */
    public function detectarCabecalhoSuspeito(Request $request): ?string
    {
        $headersToCheck = ['User-Agent', 'X-Forwarded-For', 'Referer', 'X-Api-Version'];

        foreach ($headersToCheck as $h) {
            $valor = (string) $request->header($h, '');
            if ($valor === '') {
                continue;
            }

            // Log4j / JNDI
            if (stripos($valor, '${jndi:') !== false) {
                return "Log4j JNDI em {$h}";
            }

            // Shellshock
            if (str_contains($valor, '() { :; };')) {
                return "Shellshock em {$h}";
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function extrairValoresRequisicao(Request $request): array
    {
        $valores = [];

        foreach ($request->query() as $k => $v) {
            if (is_scalar($v)) {
                $valores["get.{$k}"] = (string) $v;
            }
        }

        foreach ($request->post() as $k => $v) {
            // Ignora campos protegidos como senhas para evitar falso-positivo em senhas complexas
            if (in_array($k, ['password', 'password_confirmation', 'current_password', '_token'], true)) {
                continue;
            }

            if (is_scalar($v)) {
                $valores["post.{$k}"] = (string) $v;
            }
        }

        return $valores;
    }

    /**
     * Compila estatísticas completas para a War Room.
     *
     * @return array<string, mixed>
     */
    public function estatisticas(): array
    {
        $ultimas24h = now()->subHours(24);

        $totalBloqueios = SecurityEvent::count();
        $criticos24h = SecurityEvent::where('created_at', '>=', $ultimas24h)
            ->where('nivel', SecurityIpRule::NIVEL_CRITICO)
            ->count();
        $ipsBloqueadosAtivos = SecurityIpRule::blacklist()->ativas()->count();
        $ipsNaWhitelist = SecurityIpRule::whitelist()->ativas()->count();

        // Tipos de ameaça mais frequentes
        $distribuicao = SecurityEvent::selectRaw('evento, count(*) as total')
            ->groupBy('evento')
            ->orderByDesc('total')
            ->limit(6)
            ->pluck('total', 'evento')
            ->all();

        return [
            'status' => $this->estaAtivo(),
            'rigor' => $this->nivelRigor(),
            'total_bloqueios' => $totalBloqueios,
            'criticos_24h' => $criticos24h,
            'ips_bloqueados_ativos' => $ipsBloqueadosAtivos,
            'ips_whitelist' => $ipsNaWhitelist,
            'distribuicao' => $distribuicao,
            'nivel_ameaca_geral' => $criticos24h > 10 ? 'critico' : ($criticos24h > 0 ? 'atencao' : 'seguro'),
        ];
    }

    public function limparLogsAntigos(int $dias = 30): int
    {
        return SecurityEvent::where('created_at', '<', now()->subDays($dias))->delete();
    }

    /**
     * Resolve o usuário autenticado mesmo quando a requisição é interceptada antes do middleware de sessão.
     */
    protected function usuarioAutenticado(Request $request): ?User
    {
        $user = $request->user();
        if ($user instanceof User) {
            return $user;
        }

        if (auth()->check()) {
            $authUser = auth()->user();
            if ($authUser instanceof User) {
                return $authUser;
            }
        }

        $cookieNome = config('session.cookie');
        $sessionId = $request->cookies->get($cookieNome);
        if ($sessionId && is_string($sessionId)) {
            try {
                $encrypter = app('encrypter');
                $decryptedSessionId = $encrypter->decrypt($sessionId, false);
                if (is_string($decryptedSessionId) && str_contains($decryptedSessionId, '|')) {
                    $decryptedSessionId = explode('|', $decryptedSessionId, 2)[1] ?? $decryptedSessionId;
                }
                $handler = app('session')->driver();
                $data = $handler->read($decryptedSessionId);
                if ($data) {
                    $payload = @unserialize($data);
                    if (is_array($payload)) {
                        foreach ($payload as $key => $val) {
                            if (str_starts_with($key, 'login_web_') && is_numeric($val)) {
                                return User::find((int) $val);
                            }
                        }
                    }
                }
            } catch (\Throwable) {
                // Silencioso se o cookie não puder ser descriptografado
            }
        }

        return null;
    }
}

<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class MaintenanceService
{
    public const MODO_TOTAL = 'total';

    public const MODO_PUBLICO = 'publico_apenas';

    public const COOKIE_BYPASS = 'golden_maintenance_bypass';

    public function estaAtivo(): bool
    {
        return (string) Setting::get('manutencao_ativa', '0') === '1';
    }

    public function modo(): string
    {
        return (string) Setting::get('manutencao_modo', self::MODO_TOTAL);
    }

    public function titulo(): string
    {
        return (string) Setting::get('manutencao_titulo', 'Sistema em Manutenção Programada');
    }

    public function subtitulo(): string
    {
        return (string) Setting::get('manutencao_subtitulo', 'Estamos realizando atualizações para melhorar sua experiência.');
    }

    public function mensagem(): string
    {
        return (string) Setting::get('manutencao_mensagem', 'Nossa equipe técnica está aprimorando a infraestrutura da Golden Rastreamento. Todos os rastreadores continuam transmitindo normalmente via satélite e celular.');
    }

    public function previsaoFim(): ?Carbon
    {
        $valor = Setting::get('manutencao_previsao_fim');

        if (! $valor) {
            return null;
        }

        try {
            return Carbon::parse($valor);
        } catch (\Throwable) {
            return null;
        }
    }

    public function exibirCronometro(): bool
    {
        return (string) Setting::get('manutencao_exibir_cronometro', '1') === '1';
    }

    public function exibirPlantao(): bool
    {
        return (string) Setting::get('manutencao_exibir_plantao', '1') === '1';
    }

    public function telefone(): string
    {
        return (string) (Setting::get('manutencao_telefone') ?: Setting::get('contato_telefone', '(19) 99710-1483'));
    }

    public function email(): string
    {
        return (string) (Setting::get('manutencao_email') ?: Setting::get('contato_email', 'suporte@goldenrastreamento.com.br'));
    }

    public function exibirAnimacao(): bool
    {
        return (string) Setting::get('manutencao_exibir_animacao', '1') === '1';
    }

    public function velocidadeAnimacao(): string
    {
        $vel = (string) Setting::get('manutencao_velocidade_animacao', 'normal');

        return in_array($vel, ['suave', 'normal', 'rapida'], true) ? $vel : 'normal';
    }

    public function exibirSatelite(): bool
    {
        return (string) Setting::get('manutencao_exibir_satelite', '1') === '1';
    }

    public function exibirWhatsapp(): bool
    {
        return (string) Setting::get('manutencao_exibir_whatsapp', '1') === '1';
    }

    public function whatsappNumero(): string
    {
        $num = (string) Setting::get('manutencao_whatsapp_numero');

        if ($num !== '') {
            return preg_replace('/\D/', '', $num);
        }

        return preg_replace('/\D/', '', (string) Setting::get('contato_whatsapp', '5519997101483'));
    }

    public function whatsappMensagem(): string
    {
        return (string) Setting::get('manutencao_whatsapp_mensagem', 'Olá! Gostaria de informações durante o período de manutenção da Golden Rastreamento.');
    }

    public function exibirRedes(): bool
    {
        return (string) Setting::get('manutencao_exibir_redes', '1') === '1';
    }

    public function instagram(): string
    {
        return (string) Setting::get('manutencao_instagram', 'https://instagram.com/goldenrastreamento');
    }

    public function facebook(): string
    {
        return (string) Setting::get('manutencao_facebook', 'https://facebook.com/goldenrastreamento');
    }

    public function youtube(): string
    {
        return (string) Setting::get('manutencao_youtube', 'https://youtube.com/@goldenrastreamento');
    }

    public function linkedin(): string
    {
        return (string) Setting::get('manutencao_linkedin', '');
    }

    public function tokenBypass(): string
    {
        $token = (string) Setting::get('manutencao_token_bypass', '');

        if ($token === '') {
            $token = Str::random(32);
            Setting::set('manutencao_token_bypass', $token);
        }

        return $token;
    }

    /**
     * @return array<string>
     */
    public function ipsPermitidos(): array
    {
        $raw = (string) Setting::get('manutencao_ips_permitidos', '');
        $linhas = preg_split('/[\r\n,]+/', $raw) ?: [];

        return array_values(array_filter(array_map('trim', $linhas)));
    }

    /**
     * Avalia se a requisição atual pode ignorar a tela de manutenção.
     */
    public function podeAcessar(Request $request): bool
    {
        if (! $this->estaAtivo()) {
            return true;
        }

        // 1. O painel administrativo nunca é bloqueado para administradores
        if ($request->is('admin*') || $request->is('login*') || $request->routeIs('login*') || $request->routeIs('admin.*')) {
            $user = $request->user();
            if ($user && ($user->role === 'admin' || $user->role === 'vendedor')) {
                return true;
            }
            // Permite carregar a tela de login para que o admin possa autenticar
            if ($request->is('admin/login') || $request->is('login')) {
                return true;
            }
        }

        // 2. Modo público apenas permite clientes no portal
        if ($this->modo() === self::MODO_PUBLICO && ($request->is('portal*') || $request->routeIs('portal.*'))) {
            return true;
        }

        // 3. Verificação de Whitelist de IP
        $ip = $request->ip();
        if ($ip && in_array($ip, $this->ipsPermitidos(), true)) {
            return true;
        }

        // 4. Token secreto na query string (?golden_bypass=SEGREDO)
        $tokenEsperado = $this->tokenBypass();
        if ($request->query('golden_bypass') === $tokenEsperado) {
            return true;
        }

        // 5. Cookie de bypass persistente
        if ($request->cookie(self::COOKIE_BYPASS) === $tokenEsperado) {
            return true;
        }

        return false;
    }

    /**
     * Alterna o estado da manutenção.
     */
    public function alternarStatus(?bool $ativo = null): bool
    {
        $novo = $ativo ?? (! $this->estaAtivo());
        Setting::set('manutencao_ativa', $novo ? '1' : '0');

        return $novo;
    }

    /**
     * Salva as configurações de manutenção.
     *
     * @param  array<string, mixed>  $dados
     */
    public function salvarConfiguracoes(array $dados): void
    {
        Setting::set('manutencao_ativa', ! empty($dados['ativa']) ? '1' : '0');
        Setting::set('manutencao_modo', in_array($dados['modo'] ?? '', [self::MODO_TOTAL, self::MODO_PUBLICO], true) ? $dados['modo'] : self::MODO_TOTAL);
        Setting::set('manutencao_titulo', (string) ($dados['titulo'] ?? ''));
        Setting::set('manutencao_subtitulo', (string) ($dados['subtitulo'] ?? ''));
        Setting::set('manutencao_mensagem', (string) ($dados['mensagem'] ?? ''));
        Setting::set('manutencao_previsao_fim', (string) ($dados['previsao_fim'] ?? ''));
        Setting::set('manutencao_exibir_cronometro', ! empty($dados['exibir_cronometro']) ? '1' : '0');
        Setting::set('manutencao_exibir_plantao', ! empty($dados['exibir_plantao']) ? '1' : '0');
        Setting::set('manutencao_telefone', (string) ($dados['telefone'] ?? ''));
        Setting::set('manutencao_email', (string) ($dados['email'] ?? ''));

        Setting::set('manutencao_exibir_animacao', ! empty($dados['exibir_animacao']) ? '1' : '0');
        Setting::set('manutencao_velocidade_animacao', in_array($dados['velocidade_animacao'] ?? '', ['suave', 'normal', 'rapida'], true) ? $dados['velocidade_animacao'] : 'normal');
        Setting::set('manutencao_exibir_satelite', ! empty($dados['exibir_satelite']) ? '1' : '0');

        Setting::set('manutencao_exibir_whatsapp', ! empty($dados['exibir_whatsapp']) ? '1' : '0');
        Setting::set('manutencao_whatsapp_numero', preg_replace('/\D/', '', (string) ($dados['whatsapp_numero'] ?? '')));
        Setting::set('manutencao_whatsapp_mensagem', (string) ($dados['whatsapp_mensagem'] ?? ''));

        Setting::set('manutencao_exibir_redes', ! empty($dados['exibir_redes']) ? '1' : '0');
        Setting::set('manutencao_instagram', (string) ($dados['instagram'] ?? ''));
        Setting::set('manutencao_facebook', (string) ($dados['facebook'] ?? ''));
        Setting::set('manutencao_youtube', (string) ($dados['youtube'] ?? ''));
        Setting::set('manutencao_linkedin', (string) ($dados['linkedin'] ?? ''));

        if (! empty($dados['token_bypass'])) {
            Setting::set('manutencao_token_bypass', trim((string) $dados['token_bypass']));
        }

        if (isset($dados['ips_permitidos'])) {
            Setting::set('manutencao_ips_permitidos', trim((string) $dados['ips_permitidos']));
        }
    }
}

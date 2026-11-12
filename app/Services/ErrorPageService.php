<?php

namespace App\Services;

use App\Models\ErrorPage;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ErrorPageService
{
    /**
     * Códigos suportados pelo sistema.
     */
    public const CODIGOS = [400, 401, 403, 404, 419, 429, 500, 503];

    /**
     * Retorna os dados customizados ou padrão de uma página de erro.
     *
     * @return array<string, mixed>
     */
    public function obter(int $code): array
    {
        return Cache::rememberForever("golden_error_page_{$code}", function () use ($code): array {
            $registro = null;

            try {
                $registro = ErrorPage::where('code', $code)->first();
            } catch (\Throwable) {
                // Se o banco estiver fora do ar (ex: durante um 500 de conexão de BD), cai no fallback seguro
            }

            if ($registro && $registro->active) {
                return [
                    'code' => $registro->code,
                    'title' => $registro->title,
                    'subtitle' => $registro->subtitle ?? '',
                    'message' => $registro->message ?? '',
                    'primary_button_text' => $registro->primary_button_text ?: 'Voltar ao Início',
                    'primary_button_url' => $registro->primary_button_url ?: '/',
                    'show_whatsapp_button' => (bool) $registro->show_whatsapp_button,
                    'whatsapp_custom_text' => $registro->whatsapp_custom_text,
                    'icon' => $registro->icon ?: 'fa-triangle-exclamation',
                    'active' => true,
                    'customizado' => true,
                ];
            }

            return $this->padrao($code);
        });
    }

    /**
     * Retorna todas as páginas de erro com indicador de status.
     *
     * @return Collection<int, ErrorPage>
     */
    public function todas(): Collection
    {
        return ErrorPage::orderBy('code')->get();
    }

    /**
     * Salva a customização de uma página de erro.
     *
     * @param  array<string, mixed>  $dados
     */
    public function salvar(int $code, array $dados): ErrorPage
    {
        $pagina = ErrorPage::updateOrCreate(
            ['code' => $code],
            [
                'title' => (string) ($dados['title'] ?? ''),
                'subtitle' => isset($dados['subtitle']) ? (string) $dados['subtitle'] : null,
                'message' => isset($dados['message']) ? (string) $dados['message'] : null,
                'primary_button_text' => isset($dados['primary_button_text']) ? (string) $dados['primary_button_text'] : 'Voltar ao Início',
                'primary_button_url' => isset($dados['primary_button_url']) ? (string) $dados['primary_button_url'] : '/',
                'show_whatsapp_button' => ! empty($dados['show_whatsapp_button']),
                'whatsapp_custom_text' => isset($dados['whatsapp_custom_text']) ? (string) $dados['whatsapp_custom_text'] : null,
                'icon' => (string) ($dados['icon'] ?? 'fa-triangle-exclamation'),
                'active' => ! empty($dados['active']),
            ]
        );

        Cache::forget("golden_error_page_{$code}");
        Cache::forget('golden_error_pages_all');

        return $pagina;
    }

    /**
     * Restaura os textos padrão de fábrica de um código.
     */
    public function restaurar(int $code): bool
    {
        $padrao = $this->padrao($code);
        $padrao['active'] = true;
        unset($padrao['customizado']);

        ErrorPage::updateOrCreate(['code' => $code], $padrao);

        Cache::forget("golden_error_page_{$code}");
        Cache::forget('golden_error_pages_all');

        return true;
    }

    /**
     * Fallback com estética da Golden Rastreamento.
     *
     * @return array<string, mixed>
     */
    public function padrao(int $code): array
    {
        $whatsappPadrao = (string) Setting::get('contato_whatsapp', '5519997101483');

        $mapa = [
            400 => [
                'code' => 400,
                'title' => 'Requisição Inválida',
                'subtitle' => 'Não conseguimos processar esta solicitação.',
                'message' => 'O endereço ou os parâmetros enviados contêm dados incompreensíveis para o servidor. Verifique o link e tente novamente.',
                'primary_button_text' => 'Voltar ao Início',
                'primary_button_url' => '/',
                'show_whatsapp_button' => true,
                'whatsapp_custom_text' => 'Olá! Recebi um erro 400 no site da Golden Rastreamento.',
                'icon' => 'fa-circle-xmark',
                'active' => true,
                'customizado' => false,
            ],
            401 => [
                'code' => 401,
                'title' => 'Autenticação Necessária',
                'subtitle' => 'Identifique-se para acessar esta área restrita.',
                'message' => 'Esta seção exige credenciais ativas da Golden Rastreamento. Faça login com seus dados para continuar.',
                'primary_button_text' => 'Fazer Login',
                'primary_button_url' => '/admin/login',
                'show_whatsapp_button' => true,
                'whatsapp_custom_text' => 'Olá! Preciso de ajuda para acessar minha conta na Golden Rastreamento.',
                'icon' => 'fa-lock',
                'active' => true,
                'customizado' => false,
            ],
            403 => [
                'code' => 403,
                'title' => 'Acesso Não Autorizado',
                'subtitle' => 'Você não tem permissão para visualizar esta página.',
                'message' => 'A rota solicitada está protegida pelas políticas de segurança do sistema ou seu IP foi temporariamente restringido.',
                'primary_button_text' => 'Voltar ao Início',
                'primary_button_url' => '/',
                'show_whatsapp_button' => true,
                'whatsapp_custom_text' => 'Olá! Tentei acessar uma página e recebi erro 403 de acesso não autorizado na Golden Rastreamento.',
                'icon' => 'fa-shield-halved',
                'active' => true,
                'customizado' => false,
            ],
            404 => [
                'code' => 404,
                'title' => 'Página Não Encontrada',
                'subtitle' => 'O endereço digitado não existe ou mudou de lugar.',
                'message' => 'Não conseguimos localizar o conteúdo que você procura. Ele pode ter sido renomeado, removido ou o link contém um erro de digitação.',
                'primary_button_text' => 'Voltar ao Início',
                'primary_button_url' => '/',
                'show_whatsapp_button' => true,
                'whatsapp_custom_text' => 'Olá! Procurei uma página no site da Golden Rastreamento e recebi erro 404.',
                'icon' => 'fa-compass',
                'active' => true,
                'customizado' => false,
            ],
            419 => [
                'code' => 419,
                'title' => 'Sessão Expirada',
                'subtitle' => 'Seu token de segurança expirou por inatividade.',
                'message' => 'Para proteger sua sessão contra envios indevidos e ataques externos (CSRF), a validade expirou. Basta recarregar a tela para continuar.',
                'primary_button_text' => 'Recarregar a Página',
                'primary_button_url' => 'javascript:location.reload()',
                'show_whatsapp_button' => false,
                'whatsapp_custom_text' => null,
                'icon' => 'fa-clock-rotate-left',
                'active' => true,
                'customizado' => false,
            ],
            429 => [
                'code' => 429,
                'title' => 'Muitas Requisições Recentes',
                'subtitle' => 'Aguarde alguns instantes antes de tentar novamente.',
                'message' => 'Nosso escudo de segurança detectou muitas requisições simultâneas do seu dispositivo. Aguarde 1 minuto para o tráfego normalizar.',
                'primary_button_text' => 'Tentar Novamente',
                'primary_button_url' => 'javascript:location.reload()',
                'show_whatsapp_button' => true,
                'whatsapp_custom_text' => 'Olá! Recebi um aviso de limite de requisições no site da Golden Rastreamento.',
                'icon' => 'fa-hand',
                'active' => true,
                'customizado' => false,
            ],
            500 => [
                'code' => 500,
                'title' => 'Instabilidade no Servidor',
                'subtitle' => 'Ocorreu um erro inesperado em nosso servidor.',
                'message' => 'Nossa equipe técnica já foi alertada e está trabalhando no restabelecimento do serviço. Por favor, tente novamente em alguns instantes.',
                'primary_button_text' => 'Voltar ao Início',
                'primary_button_url' => '/',
                'show_whatsapp_button' => true,
                'whatsapp_custom_text' => 'Olá! Tentei acessar a Golden Rastreamento e recebi erro 500 no servidor.',
                'icon' => 'fa-server',
                'active' => true,
                'customizado' => false,
            ],
            503 => [
                'code' => 503,
                'title' => 'Manutenção Programada',
                'subtitle' => 'Estamos aprimorando nossos serviços para você.',
                'message' => 'Nosso sistema está passando por melhorias técnicas programadas para garantir maior velocidade, segurança e estabilidade.',
                'primary_button_text' => 'Atualizar Status',
                'primary_button_url' => 'javascript:location.reload()',
                'show_whatsapp_button' => true,
                'whatsapp_custom_text' => 'Olá! Gostaria de falar com o suporte da Golden Rastreamento durante a manutenção.',
                'icon' => 'fa-screwdriver-wrench',
                'active' => true,
                'customizado' => false,
            ],
        ];

        return $mapa[$code] ?? [
            'code' => $code,
            'title' => "Erro {$code}",
            'subtitle' => 'Ocorreu um imprevisto na requisição.',
            'message' => 'Por favor, tente novamente ou entre em contato com nosso atendimento.',
            'primary_button_text' => 'Voltar ao Início',
            'primary_button_url' => '/',
            'show_whatsapp_button' => true,
            'whatsapp_custom_text' => "Olá! Recebi um erro {$code} no site da Golden Rastreamento.",
            'icon' => 'fa-triangle-exclamation',
            'active' => true,
            'customizado' => false,
        ];
    }
}

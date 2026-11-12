<?php

namespace Database\Seeders;

use App\Models\ErrorPage;
use Illuminate\Database\Seeder;

class ErrorPageSeeder extends Seeder
{
    public function run(): void
    {
        $padroes = [
            [
                'code' => 400,
                'title' => 'Requisição Inválida',
                'subtitle' => 'Não conseguimos processar esta solicitação.',
                'message' => 'O endereço ou os parâmetros enviados contêm dados incompreensíveis para o servidor. Verifique o link e tente novamente.',
                'primary_button_text' => 'Voltar ao Início',
                'primary_button_url' => '/',
                'show_whatsapp_button' => true,
                'whatsapp_custom_text' => 'Olá! Recebi um erro de requisição no site da Golden Rastreamento e preciso de ajuda.',
                'icon' => 'fa-circle-xmark',
                'active' => true,
            ],
            [
                'code' => 401,
                'title' => 'Autenticação Necessária',
                'subtitle' => 'Identifique-se para acessar esta área restrita.',
                'message' => 'Esta seção exige autenticação com credenciais ativas da Golden Rastreamento. Faça login para continuar.',
                'primary_button_text' => 'Fazer Login',
                'primary_button_url' => '/admin/login',
                'show_whatsapp_button' => true,
                'whatsapp_custom_text' => 'Olá! Preciso de ajuda para acessar minha conta na Golden Rastreamento.',
                'icon' => 'fa-lock',
                'active' => true,
            ],
            [
                'code' => 403,
                'title' => 'Acesso Não Autorizado',
                'subtitle' => 'Você não tem permissão para visualizar esta página.',
                'message' => 'A rota solicitada está protegida pelas políticas de controle de acesso do sistema ou seu endereço foi temporariamente restrito por segurança.',
                'primary_button_text' => 'Voltar ao Início',
                'primary_button_url' => '/',
                'show_whatsapp_button' => true,
                'whatsapp_custom_text' => 'Olá! Tentei acessar uma página e recebi aviso de permissão negada na Golden Rastreamento.',
                'icon' => 'fa-shield-halved',
                'active' => true,
            ],
            [
                'code' => 404,
                'title' => 'Página Não Encontrada',
                'subtitle' => 'O endereço digitado não existe ou mudou de lugar.',
                'message' => 'Não conseguimos localizar o conteúdo que você procura. Ele pode ter sido renomeado, removido ou o link pode ter um pequeno erro de digitação.',
                'primary_button_text' => 'Voltar ao Início',
                'primary_button_url' => '/',
                'show_whatsapp_button' => true,
                'whatsapp_custom_text' => 'Olá! Procurei uma página no site da Golden Rastreamento e deu erro 404. Podem me ajudar?',
                'icon' => 'fa-compass',
                'active' => true,
            ],
            [
                'code' => 419,
                'title' => 'Sessão Expirada',
                'subtitle' => 'Seu token de segurança expirou por inatividade.',
                'message' => 'Para proteger sua sessão contra envios duplicados e ataques externos (CSRF), a validade do formulário expirou. Basta recarregar a tela para continuar com segurança.',
                'primary_button_text' => 'Recarregar a Página',
                'primary_button_url' => 'javascript:location.reload()',
                'show_whatsapp_button' => false,
                'whatsapp_custom_text' => null,
                'icon' => 'fa-clock-rotate-left',
                'active' => true,
            ],
            [
                'code' => 429,
                'title' => 'Muitas Requisições Recentes',
                'subtitle' => 'Aguarde alguns instantes antes de tentar novamente.',
                'message' => 'Nosso escudo de segurança detectou um volume elevado de requisições do seu dispositivo em curto período. Aguarde 1 minuto para o tráfego normalizar.',
                'primary_button_text' => 'Tentar Novamente',
                'primary_button_url' => 'javascript:location.reload()',
                'show_whatsapp_button' => true,
                'whatsapp_custom_text' => 'Olá! Recebi um aviso de limite de requisições no site da Golden Rastreamento.',
                'icon' => 'fa-hand',
                'active' => true,
            ],
            [
                'code' => 500,
                'title' => 'Instabilidade no Servidor',
                'subtitle' => 'Ocorreu um erro inesperado em nosso servidor.',
                'message' => 'Nossa equipe técnica já recebeu o alerta desta ocorrência e está atuando para reestabelecer o serviço. Por favor, tente novamente em alguns instantes.',
                'primary_button_text' => 'Voltar ao Início',
                'primary_button_url' => '/',
                'show_whatsapp_button' => true,
                'whatsapp_custom_text' => 'Olá! Tentei acessar a Golden Rastreamento e recebi erro 500 no servidor. Podem verificar?',
                'icon' => 'fa-server',
                'active' => true,
            ],
            [
                'code' => 503,
                'title' => 'Manutenção Programada',
                'subtitle' => 'Estamos aprimorando nossos serviços para você.',
                'message' => 'Nosso sistema está passando por melhorias técnicas programadas para garantir maior velocidade, segurança e estabilidade. Retornaremos em instantes!',
                'primary_button_text' => 'Atualizar Status',
                'primary_button_url' => 'javascript:location.reload()',
                'show_whatsapp_button' => true,
                'whatsapp_custom_text' => 'Olá! Gostaria de falar com o plantão de atendimento da Golden Rastreamento durante a manutenção.',
                'icon' => 'fa-screwdriver-wrench',
                'active' => true,
            ],
        ];

        foreach ($padroes as $p) {
            ErrorPage::updateOrCreate(['code' => $p['code']], $p);
        }
    }
}


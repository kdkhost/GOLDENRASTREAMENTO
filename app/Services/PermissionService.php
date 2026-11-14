<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;

class PermissionService
{
    public const SETTING_KEY = 'rbac_matriz_permissoes';

    public const MAX_SUPERADMINS = 2;

    /**
     * Perfis oficiais suportados pelo sistema.
     *
     * @var array<string, array{nome: string, descricao: string, cor: string, icone: string, protegido?: bool}>
     */
    public const PERFIS = [
        'superadmin' => [
            'nome' => 'Super Administrador',
            'descricao' => 'Acesso total e irrestrito a todos os módulos, exclusões críticas, permissões e segurança.',
            'cor' => 'danger',
            'icone' => 'fa-crown',
            'protegido' => true,
        ],
        'admin' => [
            'nome' => 'Administrador',
            'descricao' => 'Gestão completa da operação, vendas, catálogo, usuários e configurações.',
            'cor' => 'gold',
            'icone' => 'fa-user-shield',
        ],
        'vendedor' => [
            'nome' => 'Vendedor',
            'descricao' => 'Foco comercial: gestão de leads, clientes, pedidos, atendimentos e acompanhamento.',
            'cor' => 'blue',
            'icone' => 'fa-briefcase',
        ],
        'contador' => [
            'nome' => 'Contador',
            'descricao' => 'Foco financeiro e fiscal: visualização de pedidos, faturamento, contratos e auditoria.',
            'cor' => 'teal',
            'icone' => 'fa-calculator',
        ],
        'marketing' => [
            'nome' => 'Marketing',
            'descricao' => 'Foco em tráfego e marca: campanhas, Google Ads, funis, atribuição e conteúdo do site.',
            'cor' => 'purple',
            'icone' => 'fa-bullhorn',
        ],
        'suporte' => [
            'nome' => 'Suporte',
            'descricao' => 'Foco em pós-venda: chamados técnicos, conversas do site, assistente virtual e clientes.',
            'cor' => 'green',
            'icone' => 'fa-headset',
        ],
        'visualizador' => [
            'nome' => 'Visualizador',
            'descricao' => 'Acesso básico somente leitura aos dados operacionais principais.',
            'cor' => 'neutral',
            'icone' => 'fa-eye',
        ],
    ];

    /**
     * Módulos do sistema e suas ações configuráveis (ver, editar, deletar).
     *
     * @var array<string, array{nome: string, grupo: string, acoes: array<string>}>
     */
    public const MODULOS = [
        'leads' => [
            'nome' => 'Leads & CRM',
            'grupo' => 'Comercial',
            'acoes' => ['ver', 'editar', 'deletar'],
        ],
        'clientes' => [
            'nome' => 'Clientes',
            'grupo' => 'Comercial',
            'acoes' => ['ver', 'editar', 'deletar'],
        ],
        'campanhas' => [
            'nome' => 'Campanhas & Atribuição de Tráfego',
            'grupo' => 'Comercial',
            'acoes' => ['ver', 'editar', 'deletar'],
        ],
        'google_ads' => [
            'nome' => 'Google Ads, Copiloto & Relatórios',
            'grupo' => 'Comercial',
            'acoes' => ['ver', 'editar', 'deletar'],
        ],
        'funil' => [
            'nome' => 'Marketing (Páginas de Funil)',
            'grupo' => 'Comercial',
            'acoes' => ['ver', 'editar', 'deletar'],
        ],
        'pedidos' => [
            'nome' => 'Pedidos & Vendas',
            'grupo' => 'Vendas & Catálogo',
            'acoes' => ['ver', 'editar', 'deletar'],
        ],
        'catalogo' => [
            'nome' => 'Catálogo (Produtos, Categorias, Fornecedores)',
            'grupo' => 'Vendas & Catálogo',
            'acoes' => ['ver', 'editar', 'deletar'],
        ],
        'chamados' => [
            'nome' => 'Chamados de Suporte & Conversas do Site',
            'grupo' => 'Atendimento',
            'acoes' => ['ver', 'editar', 'deletar'],
        ],
        'assistente' => [
            'nome' => 'Assistente Virtual (Persona & Conhecimento IA)',
            'grupo' => 'Atendimento',
            'acoes' => ['ver', 'editar', 'deletar'],
        ],
        'contratos' => [
            'nome' => 'Contratos',
            'grupo' => 'Atendimento',
            'acoes' => ['ver', 'editar', 'deletar'],
        ],
        'conteudo' => [
            'nome' => 'Conteúdo do Site (Páginas, Imagens, Textos, FAQs)',
            'grupo' => 'Conteúdo & CMS',
            'acoes' => ['ver', 'editar', 'deletar'],
        ],
        'configuracoes' => [
            'nome' => 'Configurações Gerais, Loja, Correios & Chaves',
            'grupo' => 'Sistema & Segurança',
            'acoes' => ['ver', 'editar'],
        ],
        'erros_manutencao' => [
            'nome' => 'Páginas de Erro & Modo de Manutenção',
            'grupo' => 'Sistema & Segurança',
            'acoes' => ['ver', 'editar'],
        ],
        'seguranca' => [
            'nome' => 'Segurança Militar, Acesso & Crons',
            'grupo' => 'Sistema & Segurança',
            'acoes' => ['ver', 'editar', 'deletar'],
        ],
        'usuarios' => [
            'nome' => 'Usuários do Sistema',
            'grupo' => 'Sistema & Segurança',
            'acoes' => ['ver', 'editar', 'deletar'],
        ],
        'auditoria' => [
            'nome' => 'Logs de Auditoria',
            'grupo' => 'Sistema & Segurança',
            'acoes' => ['ver'],
        ],
        'permissoes' => [
            'nome' => 'Configurações de Permissões e Perfis',
            'grupo' => 'Sistema & Segurança',
            'acoes' => ['ver', 'editar'],
        ],
    ];

    /** @var array<string, array<string, array<string, bool>>>|null */
    private ?array $matrizCache = null;

    /**
     * Conta quantos Super Administradores existem no sistema (opcionalmente ignorando um usuário).
     */
    public static function totalSuperAdmins(?int $ignorarUserId = null): int
    {
        return User::where('role', 'superadmin')
            ->when($ignorarUserId !== null, fn ($q) => $q->where('id', '!=', $ignorarUserId))
            ->count();
    }

    /**
     * Retorna os rótulos simples de perfis para selects (ex: ['superadmin' => 'Super Administrador', ...]).
     *
     * @return array<string, string>
     */
    public static function opcoesPerfis(?User $ator = null, ?User $alvo = null): array
    {
        $totalOutrosSuper = self::totalSuperAdmins($alvo?->id);
        $semSuperadminAinda = self::totalSuperAdmins() === 0;

        $lista = [];
        foreach (self::PERFIS as $chave => $meta) {
            if ($chave === 'superadmin' && $ator !== null) {
                $atorPode = $ator->isSuperAdmin() || $semSuperadminAinda;
                $vagaDisponivel = ($alvo !== null && $alvo->isSuperAdmin()) || $totalOutrosSuper < self::MAX_SUPERADMINS;

                if (! $atorPode || ! $vagaDisponivel) {
                    continue;
                }
            }
            $lista[$chave] = $meta['nome'];
        }

        return $lista;
    }

    /**
     * Matriz padrão de fábrica para todos os perfis.
     *
     * @return array<string, array<string, array<string, bool>>>
     */
    public function padroes(): array
    {
        $tudo = static fn (bool $v = true, bool $e = true, bool $d = true): array => [
            'ver' => $v,
            'editar' => $e,
            'deletar' => $d,
        ];

        return [
            'superadmin' => array_fill_keys(array_keys(self::MODULOS), $tudo(true, true, true)),

            'admin' => [
                'leads' => $tudo(true, true, true),
                'clientes' => $tudo(true, true, true),
                'campanhas' => $tudo(true, true, true),
                'google_ads' => $tudo(true, true, true),
                'funil' => $tudo(true, true, true),
                'pedidos' => $tudo(true, true, true),
                'catalogo' => $tudo(true, true, true),
                'chamados' => $tudo(true, true, true),
                'assistente' => $tudo(true, true, true),
                'contratos' => $tudo(true, true, true),
                'conteudo' => $tudo(true, true, true),
                'configuracoes' => $tudo(true, true, false),
                'erros_manutencao' => $tudo(true, true, false),
                'seguranca' => $tudo(true, true, true),
                'usuarios' => $tudo(true, true, true),
                'auditoria' => $tudo(true, false, false),
                'permissoes' => $tudo(true, true, false),
            ],

            'vendedor' => [
                'leads' => $tudo(true, true, false),
                'clientes' => $tudo(true, true, false),
                'campanhas' => $tudo(true, true, false),
                'google_ads' => $tudo(true, false, false),
                'funil' => $tudo(false, false, false),
                'pedidos' => $tudo(true, true, false),
                'catalogo' => $tudo(false, false, false),
                'chamados' => $tudo(true, true, false),
                'assistente' => $tudo(false, false, false),
                'contratos' => $tudo(false, false, false),
                'conteudo' => $tudo(false, false, false),
                'configuracoes' => $tudo(false, false, false),
                'erros_manutencao' => $tudo(false, false, false),
                'seguranca' => $tudo(false, false, false),
                'usuarios' => $tudo(false, false, false),
                'auditoria' => $tudo(false, false, false),
                'permissoes' => $tudo(false, false, false),
            ],

            'contador' => [
                'leads' => $tudo(false, false, false),
                'clientes' => $tudo(true, false, false),
                'campanhas' => $tudo(false, false, false),
                'google_ads' => $tudo(false, false, false),
                'funil' => $tudo(false, false, false),
                'pedidos' => $tudo(true, false, false),
                'catalogo' => $tudo(true, false, false),
                'chamados' => $tudo(false, false, false),
                'assistente' => $tudo(false, false, false),
                'contratos' => $tudo(true, false, false),
                'conteudo' => $tudo(false, false, false),
                'configuracoes' => $tudo(false, false, false),
                'erros_manutencao' => $tudo(false, false, false),
                'seguranca' => $tudo(false, false, false),
                'usuarios' => $tudo(false, false, false),
                'auditoria' => $tudo(true, false, false),
                'permissoes' => $tudo(false, false, false),
            ],

            'marketing' => [
                'leads' => $tudo(true, false, false),
                'clientes' => $tudo(false, false, false),
                'campanhas' => $tudo(true, true, false),
                'google_ads' => $tudo(true, true, false),
                'funil' => $tudo(true, true, false),
                'pedidos' => $tudo(false, false, false),
                'catalogo' => $tudo(true, false, false),
                'chamados' => $tudo(false, false, false),
                'assistente' => $tudo(false, false, false),
                'contratos' => $tudo(false, false, false),
                'conteudo' => $tudo(true, true, false),
                'configuracoes' => $tudo(false, false, false),
                'erros_manutencao' => $tudo(false, false, false),
                'seguranca' => $tudo(false, false, false),
                'usuarios' => $tudo(false, false, false),
                'auditoria' => $tudo(false, false, false),
                'permissoes' => $tudo(false, false, false),
            ],

            'suporte' => [
                'leads' => $tudo(false, false, false),
                'clientes' => $tudo(true, true, false),
                'campanhas' => $tudo(false, false, false),
                'google_ads' => $tudo(false, false, false),
                'funil' => $tudo(false, false, false),
                'pedidos' => $tudo(true, false, false),
                'catalogo' => $tudo(false, false, false),
                'chamados' => $tudo(true, true, false),
                'assistente' => $tudo(true, true, false),
                'contratos' => $tudo(true, false, false),
                'conteudo' => $tudo(false, false, false),
                'configuracoes' => $tudo(false, false, false),
                'erros_manutencao' => $tudo(false, false, false),
                'seguranca' => $tudo(false, false, false),
                'usuarios' => $tudo(false, false, false),
                'auditoria' => $tudo(false, false, false),
                'permissoes' => $tudo(false, false, false),
            ],

            'visualizador' => [
                'leads' => $tudo(true, false, false),
                'clientes' => $tudo(true, false, false),
                'campanhas' => $tudo(false, false, false),
                'google_ads' => $tudo(false, false, false),
                'funil' => $tudo(false, false, false),
                'pedidos' => $tudo(true, false, false),
                'catalogo' => $tudo(false, false, false),
                'chamados' => $tudo(false, false, false),
                'assistente' => $tudo(false, false, false),
                'contratos' => $tudo(false, false, false),
                'conteudo' => $tudo(false, false, false),
                'configuracoes' => $tudo(false, false, false),
                'erros_manutencao' => $tudo(false, false, false),
                'seguranca' => $tudo(false, false, false),
                'usuarios' => $tudo(false, false, false),
                'auditoria' => $tudo(false, false, false),
                'permissoes' => $tudo(false, false, false),
            ],
        ];
    }

    /**
     * Retorna a matriz completa mesclando o que foi salvo no banco com os padrões de fábrica.
     *
     * @return array<string, array<string, array<string, bool>>>
     */
    public function matriz(): array
    {
        if ($this->matrizCache !== null) {
            return $this->matrizCache;
        }

        $padrao = $this->padroes();
        $salvoRaw = Setting::get(self::SETTING_KEY, '');
        $salvo = is_string($salvoRaw) && $salvoRaw !== '' ? json_decode($salvoRaw, true) : null;

        if (! is_array($salvo)) {
            return $this->matrizCache = $padrao;
        }

        $resultado = $padrao;
        foreach (array_keys(self::PERFIS) as $perfil) {
            if ($perfil === 'superadmin') {
                continue;
            }
            if (! isset($salvo[$perfil]) || ! is_array($salvo[$perfil])) {
                continue;
            }
            foreach (array_keys(self::MODULOS) as $modulo) {
                if (! isset($salvo[$perfil][$modulo]) || ! is_array($salvo[$perfil][$modulo])) {
                    continue;
                }
                foreach (['ver', 'editar', 'deletar'] as $acao) {
                    if (array_key_exists($acao, $salvo[$perfil][$modulo])) {
                        $resultado[$perfil][$modulo][$acao] = (bool) $salvo[$perfil][$modulo][$acao];
                    }
                }
            }
        }

        return $this->matrizCache = $resultado;
    }

    /**
     * Persiste a matriz de permissões enviada pelo painel.
     *
     * @param  array<string, mixed>  $input
     */
    public function salvarMatriz(array $input): void
    {
        $nova = [];

        foreach (array_keys(self::PERFIS) as $perfil) {
            if ($perfil === 'superadmin') {
                continue;
            }
            $nova[$perfil] = [];
            foreach (self::MODULOS as $modulo => $meta) {
                $nova[$perfil][$modulo] = [];
                foreach ($meta['acoes'] as $acao) {
                    $nova[$perfil][$modulo][$acao] = ! empty($input[$perfil][$modulo][$acao]);
                }
            }
        }

        Setting::set(self::SETTING_KEY, json_encode($nova, JSON_UNESCAPED_UNICODE));
        $this->matrizCache = null;
    }

    /**
     * Restaura todas as permissões para o padrão de fábrica.
     */
    public function restaurarPadroes(): void
    {
        Setting::set(self::SETTING_KEY, '');
        $this->matrizCache = null;
    }

    /**
     * Verifica se o usuário possui determinada permissão (ver, editar, deletar) em um módulo.
     */
    public function temPermissao(?User $user, string $modulo, string $acao = 'ver'): bool
    {
        if ($user === null || $user->status !== 'ativo') {
            return false;
        }

        if ($user->role === 'superadmin') {
            return true;
        }

        $matriz = $this->matriz();
        $perfil = $user->role;

        if (! isset($matriz[$perfil][$modulo])) {
            return false;
        }

        // Regra de coerência: se pode editar ou deletar, implicitamente precisa poder ver (salvo se explicitamente negado, mas aqui verificamos o flag direto)
        return (bool) ($matriz[$perfil][$modulo][$acao] ?? false);
    }

    /**
     * Resolve qual módulo do RBAC corresponde a um nome de rota do painel (`admin.*`).
     */
    public function moduloParaRota(?string $routeName): ?string
    {
        if (! is_string($routeName) || $routeName === '') {
            return null;
        }

        // Acesso supervisionado ao portal do cliente e desligamento de 2FA de outro usuário são ações de segurança
        if (str_starts_with($routeName, 'admin.clientes.portal.')
            || str_starts_with($routeName, 'admin.seguranca.destravar')
            || str_starts_with($routeName, 'admin.seguranca.dois-fatores.')
            || str_starts_with($routeName, 'admin.seguranca-militar.')
            || str_starts_with($routeName, 'admin.crons.')) {
            return 'seguranca';
        }

        // A tela pessoal de 2FA (admin.seguranca.index) é acessível a qualquer usuário autenticado do painel
        if ($routeName === 'admin.seguranca.index') {
            return null;
        }

        $mapa = [
            'admin.leads.' => 'leads',
            'admin.clientes.' => 'clientes',
            'admin.campanhas.' => 'campanhas',
            'admin.atribuicao.' => 'campanhas',
            'admin.google-ads.' => 'google_ads',
            'admin.funil.' => 'funil',
            'admin.pedidos.' => 'pedidos',
            'admin.catalogo.' => 'catalogo',
            'admin.chamados.' => 'chamados',
            'admin.assistente.conversas.' => 'chamados',
            'admin.assistente.persona' => 'assistente',
            'admin.assistente.conhecimento.' => 'assistente',
            'admin.contratos.' => 'contratos',
            'admin.conteudo.' => 'conteudo',
            'admin.settings.' => 'configuracoes',
            'admin.erros.' => 'erros_manutencao',
            'admin.manutencao.' => 'erros_manutencao',
            'admin.usuarios.' => 'usuarios',
            'admin.activities.' => 'auditoria',
            'admin.permissoes.' => 'permissoes',
        ];

        foreach ($mapa as $prefixo => $modulo) {
            if (str_starts_with($routeName, $prefixo)) {
                return $modulo;
            }
        }

        return null;
    }

    /**
     * Determina a ação exigida ('ver', 'editar' ou 'deletar') com base no método HTTP e nome da rota.
     */
    public function acaoParaRequisicao(string $method, ?string $routeName): string
    {
        $method = strtoupper($method);
        $routeName = (string) $routeName;

        if ($method === 'DELETE'
            || str_ends_with($routeName, '.destroy')
            || str_ends_with($routeName, '.delete')
            || str_ends_with($routeName, '.remover')) {
            return 'deletar';
        }

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)
            || str_ends_with($routeName, '.create')
            || str_ends_with($routeName, '.edit')
            || str_ends_with($routeName, '.nova')
            || str_ends_with($routeName, '.novo')) {
            return 'editar';
        }

        return 'ver';
    }
}

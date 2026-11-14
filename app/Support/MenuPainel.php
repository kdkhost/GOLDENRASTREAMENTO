<?php

namespace App\Support;

use App\Models\User;
use App\Services\PermissionService;

/**
 * Árvore da sidebar — a mesma para o painel e para o portal do cliente.
 *
 * O esqueleto vem de config('golden.menu_painel') ou config('golden.menu_portal') e esta
 * classe só resolve o estado da rota atual: qual item está ativo, qual grupo precisa nascer
 * aberto e quais itens o papel do usuário não vê. A view não decide nada — ela pinta o que
 * sai daqui —, então o mesmo resultado vale para o header colapsado, o mobile e o teste.
 *
 * O grupo abre sozinho o grupo da página aberta porque o AdminLTE só mostra
 * `.nav-treeview` dentro de `.menu-open`; o "um grupo por vez" depois do clique é o
 * accordion nativo do plugin (`accordion: true`), que fecha os irmãos do mesmo nível.
 */
class MenuPainel
{
    /**
     * @param  array<string,int>  $pendencias  contadores que viram bolhas
     * @param  array<string,bool>  $flags  bandeiras named ('loja_ativa' => ...)
     * @return array<int,array<string,mixed>>
     */
    public static function montar(array $pendencias, bool $isAdmin, array $flags = [], string $arvore = 'golden.menu_painel', ?User $usuario = null): array
    {
        $rotaAtual = optional(request()->route())->getName() ?: '';
        if ($usuario === null && $arvore === 'golden.menu_painel') {
            $usuario = auth()->user();
        }
        $permService = $usuario !== null && $arvore === 'golden.menu_painel'
            ? app(PermissionService::class)
            : null;

        $podeVerItem = static function (array $item, bool $herdaAdmin = false) use ($isAdmin, $usuario, $permService): bool {
            if ($permService !== null && $usuario !== null) {
                $modulo = $permService->moduloParaRota($item['rota'] ?? null);
                if ($modulo !== null) {
                    return $usuario->podeVer($modulo);
                }
            }

            $exigeAdmin = (bool) ($item['admin'] ?? $herdaAdmin);

            return ! ($exigeAdmin && ! $isAdmin);
        };

        $acende = static function (array $item) use ($rotaAtual): bool {
            $prefixo = $item['prefixo'] ?? null;

            if (is_string($prefixo) && $prefixo !== '') {
                return $rotaAtual !== '' && str_starts_with($rotaAtual, $prefixo);
            }

            return $rotaAtual !== '' && $rotaAtual === ($item['rota'] ?? null);
        };

        $montarItem = static function (array $item) use ($acende, $pendencias, $flags): array {
            $desligada = isset($item['desligada']) && ! ($flags[$item['desligada']] ?? true);
            $bolha = $item['bolha'] ?? null;

            return [
                'titulo' => $item['titulo'],
                'icone' => $item['icone'],
                'url' => isset($item['rota']) ? route($item['rota']) : '#',
                'externo' => (bool) ($item['externo'] ?? false),
                'ativo' => $acende($item),
                'esmaecido' => $desligada,
                'aviso' => $desligada ? 'desligada' : null,
                'bolha' => $bolha === null || $pendencias[$bolha] <= 0 ? null : (string) $pendencias[$bolha],
                'tom' => $item['tom'] ?? 'gold',
                'filhos' => [],
                'aberto' => false,
            ];
        };

        $grupos = [];

        foreach (config($arvore, []) as $grupo) {
            $temFilhosOriginais = isset($grupo['filhos']) && is_array($grupo['filhos']) && $grupo['filhos'] !== [];

            if (! $temFilhosOriginais && ! $podeVerItem($grupo)) {
                continue;
            }

            $render = $montarItem($grupo);
            $grupoAdmin = (bool) ($grupo['admin'] ?? false);

            foreach ($grupo['filhos'] ?? [] as $filho) {
                if (! $podeVerItem($filho, $grupoAdmin)) {
                    continue;
                }

                $render['filhos'][] = $montarItem($filho);
            }

            if ($temFilhosOriginais && $render['filhos'] === []) {
                continue;
            }

            // Grupo não é página: sem filho próprio ele é um link simples e não leva seta.
            $render['ativo'] = $render['ativo'] || collect($render['filhos'])->contains('ativo', true);
            $render['aberto'] = $render['filhos'] !== [] && $render['ativo'];
            $grupos[] = $render;
        }

        return $grupos;
    }
}

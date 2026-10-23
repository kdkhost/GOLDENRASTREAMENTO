@props(['linhas', 'titulo', 'rotulo', 'icone' => 'fa-diagram-project', 'descricao' => null, 'lider' => 1])
{{-- Uma tabela de origem do painel de atribuição. As mesmas oito colunas em todos os
     recortes (canal, rede, IA, página, site de origem): quem compara não relê layout. --}}
<x-admin.table
    :headers="[$rotulo, 'center:Visitas', 'center:Views', 'center:Cliques', 'center:Cadastros', 'center:Vendas', 'end:Conversão', 'end:Receita']"
    :title="$titulo"
    :icon="$icone"
    :descricao="$descricao"
    empty="Nada com esse filtro no período.">
    @foreach($linhas as $linha)
        <tr>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <i class="fas {{ $linha->icone }} text-gold flex-shrink-0" aria-hidden="true"></i>
                    <span class="origem-nome fw-semibold" title="{{ $linha->rotulo }}">{{ $linha->rotulo }}</span>
                    @if(($linha->pagas ?? 0) > 0)
                        <x-admin.status :rotulo="$linha->pagas.' paga(s)'" tom="red" class="flex-shrink-0" />
                    @endif
                </div>
                <div class="mini-bar tone-{{ $linha->tom }}" style="--w:{{ round(max($linha->visitas / max($lider, 1), .04) * 100) }}%"></div>
            </td>
            <td class="text-center">{{ number_format($linha->visitas, 0, ',', '.') }}</td>
            <td class="text-center text-secondary">{{ number_format($linha->views, 0, ',', '.') }}</td>
            <td class="text-center text-secondary">{{ number_format($linha->cliques, 0, ',', '.') }}</td>
            <td class="text-center">{{ $linha->leads ? number_format($linha->leads, 0, ',', '.') : '—' }}</td>
            <td class="text-center">{{ $linha->vendidos ?: '—' }}</td>
            <td class="text-end">
                <x-admin.status :rotulo="$linha->taxa.'%'" :tom="tom_por_flag($linha->taxa > 0)" />
            </td>
            <td class="text-end small">{{ $linha->receita ? brl($linha->receita) : '—' }}</td>
        </tr>
    @endforeach
</x-admin.table>

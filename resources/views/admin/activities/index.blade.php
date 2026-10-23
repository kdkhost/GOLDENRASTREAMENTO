@extends('admin.layouts.app')
@section('title', 'Auditoria')
@section('page_title', 'Trilha de auditoria')
@section('page_description', 'Registro de ações administrativas: acessos, mudanças de funil, exportações e configurações.')

@section('content')
<x-admin.filters :reset="route('admin.activities.index')">
    <x-admin.filter name="busca" label="Buscar" col="col-12 col-md-4" placeholder="Descrição, usuário ou IP..." />
    <x-admin.filter name="acao" type="select" label="Ação" col="col-6 col-md-4"
        :options="collect($acoes)->mapWithKeys(fn ($c, $a) => [$a => Str::ucfirst(str_replace('_', ' ', $a)).' ('.$c.')'])->all()"
        empty="Todas as ações" />
    <x-admin.filter name="usuario" type="select" label="Quem agiu" col="col-6 col-md-4" :options="$quem" empty="Todas as pessoas" />
</x-admin.filters>

<x-admin.table :headers="['Quando', 'Usuário', 'Ação', 'Descrição', 'IP']" empty="Sem registros de auditoria." :paginator="$atividades">
    @foreach($atividades as $a)
        <tr>
            <td class="small text-secondary text-nowrap">{{ $a->created_at->format('d/m/Y H:i') }}</td>
            <td class="small fw-semibold">{{ $a->user_name }}</td>
            <td><span class="badge tone-neutral">{{ Str::ucfirst(str_replace('_', ' ', $a->action)) }}</span></td>
            <td class="small">{{ $a->description }}</td>
            <td class="small text-secondary">{{ $a->ip }}</td>
        </tr>
    @endforeach
</x-admin.table>
@endsection

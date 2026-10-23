@extends('admin.layouts.app')
@section('title', 'Auditoria')
@section('page_title', 'Trilha de auditoria')
@section('page_description', 'Registro de ações administrativas: acessos, mudanças de funil, exportações e configurações.')

@section('content')
<x-admin.filters :reset="route('admin.activities.index')">
    <x-admin.filter name="acao" type="select" label="Ação" col="col-12 col-md-5"
        :options="collect($acoes)->mapWithKeys(fn ($c, $a) => [$a => Str::ucfirst(str_replace('_', ' ', $a)).' ('.$c.')'])->all()"
        empty="Todas as ações" />
</x-admin.filters>

<x-admin.table :headers="['Quando', 'Usuário', 'Ação', 'Descrição', 'IP']" empty="Sem registros de auditoria." :paginator="$atividades">
    @foreach($atividades as $a)
        <tr>
            <td class="small text-secondary text-nowrap">{{ $a->created_at->format('d/m/Y H:i') }}</td>
            <td class="small fw-semibold">{{ $a->user_name }}</td>
            <td><span class="badge bg-body-secondary text-body">{{ Str::ucfirst(str_replace('_', ' ', $a->action)) }}</span></td>
            <td class="small">{{ $a->description }}</td>
            <td class="small text-secondary">{{ $a->ip }}</td>
        </tr>
    @endforeach
</x-admin.table>
@endsection

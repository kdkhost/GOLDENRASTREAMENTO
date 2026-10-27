@extends('admin.layouts.app')
@section('title', $cliente->nome)
@section('page_title', $cliente->nome)
@section('page_description', \App\Models\Customer::label($cliente->status).' · '.($cliente->tipo === 'juridica' ? 'Pessoa jurídica' : 'Pessoa física'))

@php use App\Services\Whmcs\Veiculacao; @endphp

@section('page_actions')
@can('admin')
    {{-- A janela de supervisão: as telas do portal desta pessoa, abiertas daqui, em somente
         leitura. Só aparece com conta ativa — sem conta não há o que ver — e some quando o
         interruptor da tela de Configurações está desligado, para o painel não oferecer um botão
         que a rota negaria. --}}
    @if(Veiculacao::supervisao() && $cliente->temConta() && $cliente->status === 'ativo')
        <form method="POST" action="{{ route('admin.clientes.portal.entrar', $cliente) }}" class="mb-0">
            @csrf
            <button class="btn btn-sm btn-outline-secondary text-nowrap" title="Abre o portal desta pessoa, igual ao que ela vê, sem gravar nada">
                <i class="fas fa-user-shield me-1" aria-hidden="true"></i> Ver a conta do cliente
            </button>
        </form>
    @endif
@endcan
<a href="{{ route('admin.clientes.edit', $cliente) }}" class="btn btn-golden btn-sm"><i class="fas fa-pen me-1"></i> Editar ficha</a>
@if($cliente->telefone)
    <a href="https://wa.me/{{ preg_replace('/\D/', '', $cliente->telefone) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success"><i class="fab fa-whatsapp me-1"></i> WhatsApp</a>
@endif
<a href="{{ route('admin.clientes.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
@php
    $linhasEndereco = array_filter([
        $cliente->endereco,
        $cliente->cidade ? $cliente->cidade.($cliente->uf ? ' / '.$cliente->uf : '') : null,
        $cliente->cep ? 'CEP '.$cliente->cep : null,
    ]);
@endphp
<div class="row g-3">
    <div class="col-lg-5">
        <x-admin.card title="Ficha do cliente" icon="fa-address-card">
            <dl class="defs mb-0">
                <dt>Documento</dt><dd>{{ $cliente->documento ?: '—' }} ({{ $cliente->tipo === 'juridica' ? 'CNPJ' : 'CPF' }})</dd>
                <dt>WhatsApp</dt><dd>{{ $cliente->telefone ?: '—' }}</dd>
                <dt>E-mail</dt><dd>{{ $cliente->email ?: '—' }}</dd>
                <dt>Endereço</dt><dd>{{ $linhasEndereco ? implode(' · ', $linhasEndereco) : '—' }}</dd>
                <dt>Segmento</dt><dd>{{ config('golden.perfis')[$cliente->segmento] ?? '—' }}</dd>
                <dt>Frota</dt><dd>{{ $cliente->frota_tamanho ? $cliente->frota_tamanho.' veículo(s)' : '—' }}</dd>
                <dt>Valor anual</dt><dd class="fw-semibold">{{ $cliente->valor_anual_cents ? brl($cliente->valor_anual_cents) : '—' }}</dd>
                <dt>Responsável</dt><dd>{{ $cliente->owner?->name ?? 'Sem responsável definido' }}</dd>
                <dt>Portal do cliente</dt>
                <dd>
                    @if($cliente->temConta())
                        <x-admin.status rotulo="conta ativa" tom="green" />
                        <div class="form-hint fw-normal">{{ $cliente->email_portal }}@if($cliente->ultimo_acesso_em) · último acesso {{ $cliente->ultimo_acesso_em->format('d/m/Y H:i') }}@endif</div>
                    @else
                        <x-admin.status rotulo="sem conta" tom="neutral" />
                        <div class="form-hint fw-normal">Se a pessoa se cadastrar no portal, esta ficha ganha o acesso — nada é criado à mão.</div>
                    @endif
                </dd>
                <dt>No financeiro (WHMCS)</dt>
                <dd>
                    @if($cliente->whmcs_id)
                        <x-admin.status :rotulo="'conta #'.$cliente->whmcs_id" tom="green" />
                        {{-- Onde está o dado do financeiro: aqui só o número que aponta para lá.
                             Serviços e faturas não são copiados para esta base — são lidos na hora,
                             a cada visita, e obeyecem aos interruptores de veiculação. --}}
                        <div class="form-hint fw-normal">O único registro do financeiro guardado nesta ficha. Serviços e faturas são lidos na hora, a cada visita — nada fica sincronizado aqui.</div>
                    @else
                        <x-admin.status rotulo="não localizado" tom="neutral" />
                        <div class="form-hint fw-normal">A pessoa ainda não foi reconhecida no financeiro: na primeira visita ao portal a busca é feita pelo e-mail de acesso e este número aparece.</div>
                    @endif
                </dd>
                <dt>No funil</dt><dd><x-admin.status :rotulo="\App\Models\Customer::label($cliente->status)" :tom="tom_por_status('cliente', $cliente->status)" /></dd>
                @if($cliente->lead)
                    <dt>Origem</dt><dd><a href="{{ route('admin.leads.show', $cliente->lead) }}">Lead #{{ $cliente->lead_id }}</a> — {{ $cliente->lead->name }}</dd>
                @endif
                @if($cliente->observacoes)
                    <dt>Observações</dt><dd>{{ $cliente->observacoes }}</dd>
                @endif
            </dl>
            @can('admin')
                <div class="border-top mt-3 pt-3">
                    <x-admin.delete :action="route('admin.clientes.destroy', $cliente)" label="Excluir cliente" confirm="Excluir o cliente {{ $cliente->nome }}? As interações e pedidos dele também saem." />
                </div>
            @endcan
        </x-admin.card>
    </div>

    <div class="col-lg-7">
        <x-admin.card title="Registrar interação" icon="fa-comments" descricao="Fica na histórico do cliente e conta como atendimento do responsável.">
            <form method="POST" action="{{ route('admin.clientes.interacao', $cliente) }}" class="row g-3">
                @csrf
                <x-admin.field name="tipo" type="select" label="Tipo" col="col-6 col-md-4" :options="$tipos" :required="true" />
                <x-admin.field name="assunto" label="Assunto" col="col-6 col-md-8" maxlength="190" placeholder="Ex.: Envio de proposta de 12 veículos" :opcional="true" />
                <x-admin.field name="descricao" type="textarea" label="Detalhes" rows="2" maxlength="5000" placeholder="O que foi conversado, o que ficou pendente..." :opcional="true" />
                <x-admin.field name="proxima_acao_em" type="date" label="Próxima ação" col="col-8 col-md-9" :opcional="true" />
                <div class="col-4 d-flex align-items-end">
                    <button class="btn btn-golden w-100"><i class="fas fa-plus me-1"></i> Registrar</button>
                </div>
            </form>
        </x-admin.card>

        <x-admin.card title="Histórico de interações" class="mt-3"
            :descricao="$interacoes->total() ? $interacoes->total().' registro(s) — a mais recente no topo' : null">
            <ul class="timeline">
                @forelse($interacoes as $i)
                    <li class="{{ $loop->first ? 'atual' : '' }}">
                        <div class="d-flex justify-content-between gap-2">
                            <span class="fw-semibold small">
                                <i class="fas {{ $i->icone() }} me-1 text-gold" aria-hidden="true"></i>
                                {{ $i->assunto ?: $i->tipoLabel() }}
                            </span>
                            <small class="text-secondary text-nowrap">{{ $i->created_at->format('d/m/Y H:i') }}</small>
                        </div>
                        @if($i->descricao)<div class="small text-secondary">{{ $i->descricao }}</div>@endif
                        <div class="small text-secondary">
                            {{ $i->user?->name ?: 'Sistema' }} · {{ $i->tipoLabel() }}
                            @if($i->proxima_acao_em) · <span class="text-gold fw-semibold">próxima: {{ $i->proxima_acao_em->format('d/m/Y') }}</span>@endif
                        </div>
                    </li>
                @empty
                    <li class="text-secondary small">Nenhuma interação registrada ainda.</li>
                @endforelse
            </ul>
            <x-admin.pager :paginas="$interacoes" />
        </x-admin.card>

        <x-admin.table class="mt-3" :headers="['Pedido', 'center:Total', 'center:Status', 'center:Data']"
            empty="Este cliente ainda não tem pedidos na loja." :paginator="$pedidos">
            @foreach($pedidos as $o)
                <tr>
                    <td><a href="{{ route('admin.pedidos.show', $o) }}" class="text-decoration-none fw-semibold">{{ $o->numero }}</a></td>
                    <td class="text-center small">{{ brl($o->total_cents) }}</td>
                    <td class="text-center"><x-admin.status :rotulo="$o->statusLabel()" :tom="tom_por_status('pedido', $o->status)" /></td>
                    <td class="text-center small text-secondary">{{ $o->created_at->format('d/m/Y') }}</td>
                </tr>
            @endforeach
        </x-admin.table>

        <x-admin.table class="mt-3" :headers="['Chamado', 'center:Situação', 'center:Prioridade', 'center:Aberto em', 'end:Mensagens']"
            empty="Nenhum chamado aberto no portal por este cliente." :paginator="$chamados">
            @foreach($chamados as $ch)
                <tr>
                    <td><a href="{{ route('admin.chamados.show', $ch) }}" class="text-decoration-none fw-semibold">{{ $ch->assunto }}</a>
                        <div class="text-secondary small">{{ $ch->numero }} · {{ $ch->canalLabel() }}</div></td>
                    <td class="text-center"><x-admin.status :rotulo="$ch->statusLabel()" :tom="tom_por_status('chamado', $ch->status)" /></td>
                    <td class="text-center"><x-admin.status :rotulo="$ch->prioridadeLabel()" :tom="tom_por_status('prioridade', $ch->prioridade)" /></td>
                    <td class="text-center small text-secondary">{{ $ch->created_at->format('d/m/Y') }}</td>
                    <td class="text-end small">{{ $ch->messages_count }}</td>
                </tr>
            @endforeach
        </x-admin.table>

        <x-admin.table class="mt-3" :headers="['Contrato', 'center:Tipo', 'center:Situação', 'end:Mensalidade', 'center:Vigência']"
            empty="Nenhum contrato publicado para este cliente." :paginator="$contratos">
            @foreach($contratos as $ct)
                <tr>
                    <td><a href="{{ route('admin.contratos.edit', $ct) }}" class="text-decoration-none fw-semibold">{{ $ct->titulo }}</a>
                        <div class="text-secondary small">{{ $ct->numero }}</div></td>
                    <td class="text-center small">{{ $ct->tipoLabel() }}</td>
                    <td class="text-center"><x-admin.status :rotulo="$ct->statusLabel()" :tom="tom_por_status('contrato', $ct->status)" /></td>
                    <td class="text-end small">{{ $ct->valor_mensal_cents ? brl($ct->valor_mensal_cents) : '—' }}</td>
                    <td class="text-center small text-secondary">{{ $ct->celebrado_em?->format('d/m/Y') ?: '—' }}@if($ct->vigencia_ate)<div>até {{ $ct->vigencia_ate->format('d/m/Y') }}</div>@endif</td>
                </tr>
            @endforeach
        </x-admin.table>
    </div>
</div>
@endsection

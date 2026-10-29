@extends('admin.layouts.app')
@section('title', 'Códigos de recuperação')
@section('page_title', 'Guarde estes oito códigos agora')
@section('page_description', 'Eles abrem a conta uma única vez cada, quando o celular não estiver na mão: bateria no fim, aparelho trocado, roubado ou quebrado.')

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        <x-admin.card title="Os códigos desta conta" icon="fa-file-shield"
            descricao="Cada um vale uma vez só e se apaga depois de usado. Um código queimado não vale nada para quem o encontrar.">
            <div class="codigos-recuperacao">
                @foreach($codigos as $codigo)
                    <code>{{ $codigo }}</code>
                @endforeach
            </div>

            <div class="mt-4">
                <label class="form-label" for="codigos-todos">
                    Todos de uma vez
                    <span class="form-hint">(para colar num gerenciador de senhas ou imprimir)</span>
                </label>
                <div class="input-group input-group-sm">
                    <textarea id="codigos-todos" class="form-control font-monospace" rows="4" readonly>{{ implode("\n", $codigos) }}</textarea>
                    <button class="btn btn-outline-secondary" type="button" data-copiar="#codigos-todos">
                        <i class="fas fa-copy" aria-hidden="true"></i> Copiar
                    </button>
                </div>
            </div>

            <form method="POST" action="{{ route('dois-fatores.codigos.guardar') }}" class="mt-4 d-flex flex-wrap gap-2">
                @csrf
                <button class="btn btn-golden"><i class="fas fa-check me-1"></i> Já guardei estes códigos</button>
                <a href="{{ route('admin.seguranca.index') }}" class="btn btn-outline-secondary">Voltar à Segurança do acesso</a>
            </form>
        </x-admin.card>
    </div>

    <div class="col-lg-4">
        <x-admin.card title="Por que esta folha aparece uma vez" icon="fa-circle-info">
            <ul class="mb-0 ps-3 small text-secondary">
                <li>Depois de guardados, os códigos não voltam a ser mostrados em lugar nenhum — nem nesta tela,
                    nem no e-mail, nem no banco legível. O que fica gravado é cifrado, e só a sua conta abre.</li>
                <li>Perdeu a folha? Um clique em <strong>Renovar códigos</strong> na tela de Segurança gera oito
                    novos e <strong>anula os oito antigos</strong>, inclusive os que alguém já tivesse copiado.</li>
                <li>Guarde fora do celular que tem o aplicativo. Os dois no mesmo bolso não são duas chaves:
                    são uma chave com dois nomes.</li>
            </ul>
        </x-admin.card>
    </div>
</div>
@endsection

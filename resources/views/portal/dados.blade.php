@extends('portal.layout')

@section('title', 'Meus dados — Portal do cliente')

@section('content')
<div class="portal-titulo">
    <div>
        <h1>Meus dados</h1>
        <p>O que a equipe tem na sua ficha — e o que você mantém atualizado por aqui.</p>
    </div>
</div>

<div class="row-2">
    <div class="bloco">
        <div class="bloco-cab"><h2>Cadastro</h2></div>
        <form method="post" action="{{ route('portal.dados.salvar') }}" novalidate>
            @csrf
            <div class="field">
                <label for="poNome">Nome / razão social</label>
                <input id="poNome" name="nome" type="text" maxlength="120" required value="{{ old('nome', $cliente->nome) }}">
                @error('nome')<span class="form-err">{{ $message }}</span>@enderror
            </div>

            <div class="row-2">
                <div class="field">
                    <label for="poTelefone">Telefone / WhatsApp</label>
                    <input id="poTelefone" name="telefone" type="tel" maxlength="32" required value="{{ old('telefone', $cliente->telefone) }}">
                    @error('telefone')<span class="form-err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="poEmail">E-mail de contato</label>
                    <input id="poEmail" name="email" type="email" maxlength="160" value="{{ old('email', $cliente->email) }}">
                    <span class="senha-dica">Muda só o contato; o login continua sendo {{ $cliente->email_portal }}</span>
                    @error('email')<span class="form-err">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="field">
                <label for="poEndereco">Endereço</label>
                <input id="poEndereco" name="endereco" type="text" maxlength="160" value="{{ old('endereco', $cliente->endereco) }}">
                @error('endereco')<span class="form-err">{{ $message }}</span>@enderror
            </div>

            <div class="row-2">
                <div class="field">
                    <label for="poCidade">Cidade</label>
                    <input id="poCidade" name="cidade" type="text" maxlength="96" value="{{ old('cidade', $cliente->cidade) }}">
                    @error('cidade')<span class="form-err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="poUf">UF</label>
                    <input id="poUf" name="uf" type="text" maxlength="2" value="{{ old('uf', $cliente->uf) }}">
                    @error('uf')<span class="form-err">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="field">
                <label for="poCep">CEP</label>
                <input id="poCep" name="cep" type="text" maxlength="12" inputmode="numeric" value="{{ old('cep', $cliente->cep) }}">
                @error('cep')<span class="form-err">{{ $message }}</span>@enderror
            </div>

            <button type="submit" class="btn btn-gold">Salvar cadastro</button>
        </form>
    </div>

    <div>
        <div class="bloco">
            <div class="bloco-cab"><h2>Documento e conta</h2></div>
            <table class="tabela">
                <tbody>
                    <tr><th>CPF / CNPJ</th><td>{{ $cliente->documento ?: 'Não informado' }}</td></tr>
                    <tr><th>E-mail de acesso</th><td>{{ $cliente->email_portal }}</td></tr>
                    <tr><th>Cliente desde</th><td>{{ $cliente->created_at->format('m/Y') }}</td></tr>
                    <tr><th>Último acesso</th><td>{{ $cliente->ultimo_acesso_em ? $cliente->ultimo_acesso_em->format('d/m/Y \à\s H:i') : 'Ainda não registrado' }}</td></tr>
                    <tr><th>Total investido</th><td>{{ brl($total_gasto_cents) }}</td></tr>
                </tbody>
            </table>
            <p class="fine" style="margin-top:12px">Mudar CPF/CNPJ ou o e-mail de acesso é com a equipe, na ficha do
                <a href="{{ route('site.contato') }}" style="color:var(--gold-2);font-weight:700">contato</a> — é por eles que pedidos e contratos ficam ligados à sua conta.</p>
        </div>

        <div class="bloco">
            <div class="bloco-cab"><h2>Senha do portal</h2></div>
            <form method="post" action="{{ route('portal.senha.salvar') }}" novalidate>
                @csrf
                <div class="field">
                    <label for="poAtual">Senha atual</label>
                    <input id="poAtual" name="senha_atual" type="password" autocomplete="current-password" required>
                    @error('senha_atual')<span class="form-err">{{ $message }}</span>@enderror
                </div>
                <div class="row-2">
                    <div class="field">
                        <label for="poNova">Senha nova</label>
                        <input id="poNova" name="password" type="password" autocomplete="new-password" required minlength="8">
                        @error('password')<span class="form-err">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="poRepete">Repetir a nova</label>
                        <input id="poRepete" name="password_confirmation" type="password" autocomplete="new-password" required minlength="8">
                    </div>
                </div>
                <button type="submit" class="btn btn-dark">Trocar a senha</button>
            </form>
        </div>
    </div>
</div>
@endsection

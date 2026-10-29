@extends('admin.layouts.app')
@section('title', 'Verificação em duas etapas')
@section('page_title', 'Acender a segunda chave')
@section('page_description', 'Um aplicativo de autenticação no seu celular passa a ser obrigatório junto com a senha. Leva um minuto e é a defesa que sobra quando a senha vaza.')

@section('page_actions')
<a href="{{ route('admin.seguranca.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Segurança do acesso</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-5">
        <x-admin.card title="Leia o código com o aplicativo" icon="fa-qrcode"
            descricao="Google Authenticator, Microsoft Authenticator, Authy, Bitwarden e 1Password entendem todos este desenho.">
            <div class="qr-golden d-flex justify-content-center">{!! $qr !!}</div>
            <p class="form-text mt-3 mb-0">
                O desenho é gerado agora, nesta página, e não vira arquivo: uma imagem de chave secreta publicada
                em endereço público — ou guardada no cache do navegador depois que você sai da tela — é a chave de
                alguém entregue a quem chegar depois.
            </p>
            <div class="mt-3">
                <x-admin.link-copiar :valor="$segredo" label="Chave para digitar à mão" hint="(só se o celular não conseguir ler o desenho)" />
            </div>
        </x-admin.card>
    </div>

    <div class="col-lg-7">
        <x-admin.card title="Confirme com o código de agora" icon="fa-key" class="mb-3">
            <form method="POST" action="{{ route('dois-fatores.confirmar') }}" data-confirmar="Ativar a verificação em duas etapas nesta conta?">
                @csrf
                <div class="row g-3 align-items-end">
                    <x-admin.field name="codigo" label="Código de seis dígitos" col="col-12 col-md-5"
                        :required="true" autocomplete="one-time-code" maxlength="10" inputmode="numeric"
                        placeholder="000000" hint="Os seis números que o aplicativo está mostrando agora." />
                    <div class="col-12 col-md-7">
                        <button class="btn btn-golden"><i class="fas fa-shield-halved me-1"></i> Acender a segunda chave</button>
                    </div>
                </div>
                <p class="form-text mt-3 mb-0">
                    A chave só entra na sua conta depois deste teste. Sem ele, qualquer pessoa com acesso ao seu
                    navegador acenderia uma segunda porta que abre no celular <em>dela</em> — e a conta passaria a
                    pedir código para você, não para quem a fechou.
                </p>
                <p class="form-text mb-0">
                    Este código expira em {{ $validade }} minutos. Passou, abra uma nova chave: o aplicativo lê o
                    desenho novo e pronto.
                </p>
            </form>
        </x-admin.card>

        <x-admin.card title="O que muda depois" icon="fa-circle-info">
            <ul class="mb-0 ps-3 small text-secondary">
                <li>A tela de login pede a senha <strong>e</strong> o código. A senha sozinha não abre mais nada.</li>
                <li>Você recebe oito códigos de recuperação de uso único. Guarde-os fora do celular: são a porta
                    para o dia em que a bateria acabar, o aparelho quebrar ou for roubado.</li>
                <li>A troca de senha fecha toda janela aberta desta conta, inclusive a que um ladrão de cookie
                    estivesse usando.</li>
                <li>Nada disso depende do computador: o cálculo do código acontece dentro do aplicativo, no bolso.</li>
            </ul>
        </x-admin.card>
    </div>
</div>
@endsection

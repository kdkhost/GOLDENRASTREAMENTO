{{--
    Assinatura de quem construiu o sistema, no canto direito do rodapé. Nome, agência e
    endereços vêm de config('golden.credito') (a mesma fonte do composer.json): a view não
    escreve URL nenhuma. Site e funil incluem este único arquivo.
--}}
@php $credito = $golden['credito']; @endphp

<span class="foot-dev">
    <i class="fa-solid fa-code" aria-hidden="true"></i>
    Desenvolvido por
    <a href="{{ $credito['site'] }}" target="_blank" rel="noopener">{{ $credito['desenvolvedor'] }}</a>
    <span aria-hidden="true">·</span>
    <a href="{{ $credito['agencia_site'] }}" target="_blank" rel="noopener">{{ $credito['agencia'] }}</a>
</span>

<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Trocar de personagem nos testes é trocar de navegador.
     *
     * O `actingAs` monta o usuário direto no guarda, sem passar pela porta de login, e a sessão dos
     * testes é a mesma `Store` entre pedidos: o carimbo do pedido anterior continuaria lá. Com a
     * blindagem de acesso, isso tem consequência de verdade — `authenticateSessions()` derruba a
     * sessão quando a cifra da conta muda (o que é certo num navegador) e o atestado do painel
     * expulsa quem aponta para a janela de outro. Num teste, os dois virariam o pedido recusado sem
     * que ninguém tivesse feito nada de errado.
     *
     * Em produção o efeito é nulo: todo login real passa por `session()->regenerate()`, que já leva
     * esses carimbos embora.
     */
    public function be($user, $guard = null)
    {
        parent::be($user, $guard);

        $sessao = $this->app['session.store'];

        $sessao->forget(array_values(array_filter(
            array_keys($sessao->all()),
            fn (string $chave): bool => $chave === 'janela_painel' || str_starts_with($chave, 'password_hash_'),
        )));

        return $this;
    }

    /**
     * O HTML de uma tela sem a ajuda guardada. A gaveta de ajuda carrega o texto do guia dentro de
     * `<template>` na própria página, e o navegador não desenha nada disso: o que a tela oferece ou
     * esconde para quem a usa é o que sobra depois de tirar os blocos guardados.
     */
    protected function visivel(string $html): string
    {
        return (string) preg_replace('#<template\b[^>]*>.*?</template>#s', '', $html);
    }
}

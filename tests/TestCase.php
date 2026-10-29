<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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

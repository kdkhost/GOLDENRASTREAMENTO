<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('golden:rastreamentos:atualizar')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->description('Atualiza o rastreamento em tempo real dos pedidos despachados e notifica o administrador.');

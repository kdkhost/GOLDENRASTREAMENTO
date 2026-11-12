@php
    $dados = app(\App\Services\ErrorPageService::class)->obter(429);
@endphp
@extends('errors.layout', ['dados' => $dados])


@php
    $dados = app(\App\Services\ErrorPageService::class)->obter(419);
@endphp
@extends('errors.layout', ['dados' => $dados])


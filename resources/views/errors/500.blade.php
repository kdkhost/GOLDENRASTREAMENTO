@php
    $dados = app(\App\Services\ErrorPageService::class)->obter(500);
@endphp
@extends('errors.layout', ['dados' => $dados])


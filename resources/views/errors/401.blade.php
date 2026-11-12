@php
    $dados = app(\App\Services\ErrorPageService::class)->obter(401);
@endphp
@extends('errors.layout', ['dados' => $dados])


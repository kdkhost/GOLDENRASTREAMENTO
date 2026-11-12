@php
    $dados = app(\App\Services\ErrorPageService::class)->obter(400);
@endphp
@extends('errors.layout', ['dados' => $dados])


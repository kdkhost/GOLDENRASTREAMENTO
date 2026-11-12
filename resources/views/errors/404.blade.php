@php
    $dados = app(\App\Services\ErrorPageService::class)->obter(404);
@endphp
@extends('errors.layout', ['dados' => $dados])

@php
    $dados = app(\App\Services\ErrorPageService::class)->obter(403);
@endphp
@extends('errors.layout', ['dados' => $dados])


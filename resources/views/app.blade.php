@php
    $settings = app(\App\Config\AppSettings::class);
    $theme = $settings->theme();
    $darkBackground = $settings->darkBackground();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $theme }}" class="h-full dark:bg-dark-page"@if($darkBackground) data-dark-bg style="--app-dark-bg: {{ $darkBackground }}"@endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>{{ config('app.name') }}</title>
    <base href="{{ asset('') }}">
    <link rel="icon" type="image/png" sizes="32x32"  href="icons/rss-32.png">
    <link rel="icon" type="image/png" sizes="128x128"  href="icons/rss-128.png">
    <link rel="alternate" type="application/rss+xml" title="{{ config('app.name') }} – Starred" href="{{ url('favorites') }}">

    @if(!app()->runningUnitTests())
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    @inertiaHead
</head>
<body class="h-full">
@inertia
</body>
</html>

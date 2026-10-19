@php
    $theme = strtolower(trim((string) config('app.theme')));
    if (!in_array($theme, ['system', 'dark', 'light'], true)) {
        $theme = 'system';
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $theme }}" class="h-full dark:bg-gray-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>{{ config('app.name') }}</title>
    <base href="{{ asset('') }}">
    <link rel="icon" type="image/png" sizes="32x32"  href="icons/rss-32.png">
    <link rel="icon" type="image/png" sizes="128x128"  href="icons/rss-128.png">

    @if(!app()->runningUnitTests())
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    @inertiaHead
</head>
<body class="h-full">
@inertia
</body>
</html>

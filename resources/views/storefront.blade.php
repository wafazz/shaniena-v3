<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- PWA: theme-color is #e53637 in the source, distinct from Ashion's
         #ca1515 CSS primary. Both are kept as they are. --}}
    <meta name="theme-color" content="#e53637">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/png" href="/storefront/img/logo.png">
    <link rel="apple-touch-icon" href="/storefront/img/apple-touch-icon.png">

    {{-- The faces the chosen theme is set in — Montserrat and Cookie for
         Ashion, Roboto for Electro. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="{{ $themeFonts }}" rel="stylesheet">

    {{-- No <title> here: @inertiaHead emits it, and a second one would win
         with crawlers. --}}

    @routes
    {{-- One theme's stylesheet, never both: the shop that is not switched on
         is not downloaded. --}}
    @vite([$themeCss, 'resources/js/storefront.js'])
    @inertiaHead
</head>
<body class="theme-{{ $theme }}">
    @inertia
</body>
</html>

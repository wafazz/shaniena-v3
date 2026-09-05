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

    {{-- Montserrat and Cookie, the two faces Ashion is set in. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cookie&family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    {{-- No <title> here: @inertiaHead emits it, and a second one would win
         with crawlers. --}}

    @routes
    @vite(['resources/sass/storefront.scss', 'resources/js/storefront.js'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>

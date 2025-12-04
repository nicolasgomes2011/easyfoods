<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-white dark:bg-zinc-800">
<main class="min-h-screen flex items-center justify-center p-6">
    {{ $slot }}
</main>

@fluxScripts
</body>
</html>

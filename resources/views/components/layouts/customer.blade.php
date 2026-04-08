<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>{{ $title ?? config('app.name') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
    </head>
    <body class="min-h-screen bg-zinc-50">

        {{-- Top navigation --}}
        <header class="sticky top-0 z-50 w-full border-b border-zinc-200 bg-white shadow-sm">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">

                <a href="{{ route('store.home') }}" class="flex items-center gap-2" wire:navigate>
                    <x-app-logo class="size-7" href="#" />
                    <span class="text-base font-semibold text-zinc-800">EasyFoods</span>
                </a>

                <nav class="hidden items-center gap-6 text-sm font-medium text-zinc-600 sm:flex">
                    <a href="{{ route('store.menu') }}" class="hover:text-zinc-900" wire:navigate>Cardápio</a>
                </nav>

                <div class="flex items-center gap-3">
                    {{-- Cart indicator --}}
                    <a href="{{ route('store.cart') }}" class="relative flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm font-medium text-zinc-700 hover:bg-zinc-100" wire:navigate>
                        <flux:icon.shopping-cart class="size-5" />
                        <span class="sr-only">Carrinho</span>
                    </a>

                    {{-- Customer auth --}}
                    @auth('customer')
                        <a href="{{ route('account.orders') }}" class="text-sm font-medium text-zinc-700 hover:text-zinc-900" wire:navigate>
                            Meus pedidos
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="text-sm font-medium text-zinc-700 hover:text-zinc-900" wire:navigate>
                            Entrar
                        </a>
                    @endauth
                </div>

            </div>
        </header>

        {{-- Main content --}}
        <main class="mx-auto max-w-5xl px-4 py-6">
            {{ $slot }}
        </main>

        {{-- Footer --}}
        <footer class="mt-12 border-t border-zinc-200 bg-white py-6 text-center text-xs text-zinc-400">
            &copy; {{ date('Y') }} EasyFoods. Todos os direitos reservados.
        </footer>

        @fluxScripts
    </body>
</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">

        <flux:sidebar sticky stashable class="border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('admin.dashboard') }}" class="mr-5 flex items-center gap-2" wire:navigate>
                <x-app-logo class="size-8" href="#" />
                <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">EasyFoods</span>
            </a>

            <flux:navlist variant="outline">

                <flux:navlist.item
                    icon="squares-2x2"
                    :href="route('admin.dashboard')"
                    :current="request()->routeIs('admin.dashboard')"
                    wire:navigate>
                    Dashboard
                </flux:navlist.item>

                <flux:navlist.item
                    icon="clipboard-document-list"
                    :href="route('admin.orders.index')"
                    :current="request()->routeIs('admin.orders.*')"
                    wire:navigate>
                    Pedidos
                </flux:navlist.item>

                @if(auth()->user()?->role?->canManageCatalog())
                    <flux:navlist.group heading="Cardápio" class="grid">
                        <flux:navlist.item
                            icon="tag"
                            :href="route('admin.catalog.categories')"
                            :current="request()->routeIs('admin.catalog.categories')"
                            wire:navigate>
                            Categorias
                        </flux:navlist.item>

                        <flux:navlist.item
                            icon="shopping-bag"
                            :href="route('admin.catalog.products')"
                            :current="request()->routeIs('admin.catalog.products*')"
                            wire:navigate>
                            Produtos
                        </flux:navlist.item>
                    </flux:navlist.group>
                @endif

                @if(auth()->user()?->role?->canManageSettings())
                    <flux:navlist.group heading="Configurações" class="grid">
                        <flux:navlist.item
                            icon="cog-6-tooth"
                            :href="route('admin.settings.store')"
                            :current="request()->routeIs('admin.settings.*')"
                            wire:navigate>
                            Loja
                        </flux:navlist.item>
                    </flux:navlist.group>
                @endif

                @if(auth()->user()?->isAdmin())
                    <flux:navlist.item
                        icon="users"
                        :href="route('admin.users.index')"
                        :current="request()->routeIs('admin.users.*')"
                        wire:navigate>
                        Usuários
                    </flux:navlist.item>
                @endif

            </flux:navlist>

            <flux:spacer />

            <flux:dropdown position="bottom" align="start">
                <flux:profile
                    :name="auth()?->user()?->name"
                    :initials="auth()?->user()?->initials()"
                    icon-trailing="chevrons-up-down"
                />

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>
                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()?->user()?->name }}</span>
                                    <span class="truncate text-xs text-zinc-500">{{ auth()?->user()?->role?->label() }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            Sair
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <!-- Mobile header -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
            <flux:spacer />
            <span class="text-sm font-semibold">EasyFoods Admin</span>
        </flux:header>

        <flux:main>
            {{ $slot }}
        </flux:main>

        @fluxScripts
    </body>
</html>

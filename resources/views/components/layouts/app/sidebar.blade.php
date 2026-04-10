<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-950">

        <flux:sidebar sticky stashable class="border-r border-zinc-800 bg-zinc-900">

            {{-- Brand --}}
            <div class="flex items-center px-1 py-1 mb-2">
                <flux:sidebar.toggle class="lg:hidden mr-2" icon="x-mark" />
                <a href="{{ route('dashboard') }}" class="flex items-center" wire:navigate>
                    <x-app-logo />
                </a>
            </div>

            <flux:navlist variant="outline" class="space-y-0.5">

                {{-- ── OPERAÇÃO ─────────────────────────────── --}}
                <flux:navlist.group heading="Operação" class="grid">

                    <flux:navlist.item
                        icon="home"
                        :href="route('dashboard')"
                        :current="request()->routeIs('dashboard')"
                        wire:navigate
                    >
                        Dashboard
                    </flux:navlist.item>

                    <flux:navlist.group
                        heading="Pedidos"
                        expandable
                        :expanded="request()->routeIs('admin.orders.*')"
                    >
                        <flux:navlist.item
                            :href="route('admin.orders.index')"
                            :current="request()->routeIs('admin.orders.index')"
                            wire:navigate
                        >
                            Todos os pedidos
                        </flux:navlist.item>
                        <flux:navlist.item
                            :href="route('admin.orders.in-progress')"
                            :current="request()->routeIs('admin.orders.in-progress')"
                            wire:navigate
                        >
                            Em andamento
                        </flux:navlist.item>
                        <flux:navlist.item
                            :href="route('admin.orders.history')"
                            :current="request()->routeIs('admin.orders.history')"
                            wire:navigate
                        >
                            Histórico
                        </flux:navlist.item>
                    </flux:navlist.group>

                    <flux:navlist.group
                        heading="Salão"
                        expandable
                        :expanded="request()->routeIs('admin.dining.*')"
                    >
                        <flux:navlist.item
                            :href="route('admin.dining.tables')"
                            :current="request()->routeIs('admin.dining.tables')"
                            wire:navigate
                        >
                            Mesas
                        </flux:navlist.item>
                        <flux:navlist.item
                            :href="route('admin.dining.queue')"
                            :current="request()->routeIs('admin.dining.queue')"
                            wire:navigate
                        >
                            Fila de espera
                        </flux:navlist.item>
                    </flux:navlist.group>

                    <flux:navlist.item
                        icon="fire"
                        :href="route('admin.kitchen.index')"
                        :current="request()->routeIs('admin.kitchen.*')"
                        wire:navigate
                    >
                        Cozinha
                    </flux:navlist.item>

                </flux:navlist.group>

                {{-- ── GESTÃO ───────────────────────────────── --}}
                <flux:navlist.group heading="Gestão" class="grid">

                    <flux:navlist.group
                        heading="Cardápio"
                        expandable
                        :expanded="request()->routeIs('admin.catalog.*')"
                    >
                        <flux:navlist.item
                            :href="route('admin.catalog.products')"
                            :current="request()->routeIs('admin.catalog.products')"
                            wire:navigate
                        >
                            Produtos
                        </flux:navlist.item>
                        <flux:navlist.item
                            :href="route('admin.catalog.categories')"
                            :current="request()->routeIs('admin.catalog.categories')"
                            wire:navigate
                        >
                            Categorias
                        </flux:navlist.item>
                        <flux:navlist.item
                            :href="route('admin.catalog.addons')"
                            :current="request()->routeIs('admin.catalog.addons')"
                            wire:navigate
                        >
                            Complementos
                        </flux:navlist.item>
                    </flux:navlist.group>

                    <flux:navlist.item
                        icon="users"
                        :href="route('admin.customers.index')"
                        :current="request()->routeIs('admin.customers.*')"
                        wire:navigate
                    >
                        Clientes
                    </flux:navlist.item>

                    <flux:navlist.item
                        icon="chart-bar"
                        :href="route('admin.reports.index')"
                        :current="request()->routeIs('admin.reports.*')"
                        wire:navigate
                    >
                        Relatórios
                    </flux:navlist.item>

                </flux:navlist.group>

                {{-- ── CONFIGURAÇÃO ─────────────────────────── --}}
                <flux:navlist.group heading="Configuração" class="grid">

                    <flux:navlist.group
                        heading="Restaurante"
                        expandable
                        :expanded="request()->routeIs('admin.settings.*')"
                    >
                        <flux:navlist.item
                            :href="route('admin.settings.store')"
                            :current="request()->routeIs('admin.settings.store')"
                            wire:navigate
                        >
                            Perfil do restaurante
                        </flux:navlist.item>
                        <flux:navlist.item
                            :href="route('admin.settings.hours')"
                            :current="request()->routeIs('admin.settings.hours')"
                            wire:navigate
                        >
                            Horários de funcionamento
                        </flux:navlist.item>
                        <flux:navlist.item
                            :href="route('admin.settings.delivery')"
                            :current="request()->routeIs('admin.settings.delivery')"
                            wire:navigate
                        >
                            Entrega e taxas
                        </flux:navlist.item>
                    </flux:navlist.group>

                    <flux:navlist.item
                        icon="users"
                        :href="route('admin.users.index')"
                        :current="request()->routeIs('admin.users.*')"
                        wire:navigate
                    >
                        Usuários e papéis
                    </flux:navlist.item>

                    <flux:navlist.item
                        icon="cog"
                        :href="route('settings.profile')"
                        :current="request()->routeIs('settings.profile')"
                        wire:navigate
                    >
                        Meu perfil
                    </flux:navlist.item>

                </flux:navlist.group>

            </flux:navlist>

            <flux:spacer />

            {{-- User profile --}}
            <flux:dropdown position="bottom" align="start">
                <flux:profile
                    :name="auth()?->user()?->name"
                    :initials="auth()?->user()?->initials()"
                    icon-trailing="chevrons-up-down"
                />

                <flux:menu class="w-56">
                    <flux:menu.radio.group>
                        <div class="flex items-center gap-2 px-2 py-2">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-zinc-700 text-sm font-medium text-white">
                                {{ auth()?->user()?->initials() }}
                            </span>
                            <div class="grid flex-1 text-left text-sm leading-tight">
                                <span class="truncate font-semibold">{{ auth()?->user()?->name }}</span>
                                <span class="truncate text-xs text-zinc-400">{{ auth()?->user()?->email }}</span>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.item href="{{ route('settings.profile') }}" icon="cog" wire:navigate>
                        Configurações da conta
                    </flux:menu.item>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full text-red-400 hover:text-red-300">
                            Sair
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>

        </flux:sidebar>

        {{-- Mobile header --}}
        <flux:header class="lg:hidden border-b border-zinc-800 bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
            <a href="{{ route('dashboard') }}" class="flex items-center ml-2" wire:navigate>
                <x-app-logo />
            </a>
            <flux:spacer />
            <flux:dropdown position="top" align="end">
                <flux:profile :initials="auth()?->user()?->initials()" icon-trailing="chevron-down" />
                <flux:menu>
                    <flux:menu.item href="{{ route('settings.profile') }}" icon="cog" wire:navigate>Meu perfil</flux:menu.item>
                    <flux:menu.separator />
                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">Sair</flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>

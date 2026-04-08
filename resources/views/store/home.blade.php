<x-layouts.customer>
    <div class="text-center py-16">
        <h1 class="text-3xl font-bold text-zinc-800">Bem-vindo ao EasyFoods</h1>
        <p class="mt-2 text-zinc-500">Peça online de forma simples e rápida.</p>
        <a href="{{ route('store.menu') }}" wire:navigate
           class="mt-6 inline-block rounded-lg bg-zinc-900 px-6 py-3 text-sm font-semibold text-white hover:bg-zinc-700">
            Ver cardápio
        </a>
    </div>
</x-layouts.customer>

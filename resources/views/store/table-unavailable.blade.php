<x-layouts.customer>
    <div class="text-center py-16">
        <p class="text-sm font-medium text-zinc-400">Mesa {{ $table->number }}</p>
        <h1 class="mt-1 text-2xl font-bold text-zinc-800">Não dá para pedir por esta mesa agora</h1>
        <p class="mt-3 text-zinc-600">{{ $message }}</p>
        <a href="{{ route('store.menu') }}" wire:navigate
           class="mt-6 inline-block rounded-lg bg-zinc-900 px-6 py-3 text-sm font-semibold text-white hover:bg-zinc-700">
            Ver cardápio
        </a>
    </div>
</x-layouts.customer>

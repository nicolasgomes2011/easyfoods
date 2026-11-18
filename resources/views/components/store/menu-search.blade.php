{{-- Menu quick search. Alpine only (x-model on the parent store.menu x-data): no request while typing. --}}
<div class="relative mb-3">
    <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
    <input type="text" x-model="q" x-ref="search" data-menu-search
           placeholder="Buscar no cardápio" aria-label="Buscar no cardápio" autocomplete="off"
           class="w-full rounded-xl border border-zinc-200 bg-white py-2 pl-9 pr-20 text-sm text-zinc-800 placeholder:text-zinc-400 focus:border-orange-400 focus:outline-none focus:ring-1 focus:ring-orange-400" />
    <button type="button" x-show="q" style="display: none" @click="q = ''; $refs.search.focus()"
            class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg px-2 py-1 text-xs font-medium text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700">
        Limpar
    </button>
</div>

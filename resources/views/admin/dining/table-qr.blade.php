<x-layouts.app>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">QR Code — Mesa {{ $table->number }}</h1>
            <p class="text-sm text-zinc-400 mt-0.5">O cliente aponta a câmera e abre o cardápio já nesta mesa.</p>
        </div>
        <a href="{{ route('admin.dining.tables') }}" wire:navigate
           class="text-sm text-zinc-300 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 rounded-lg px-3 py-1.5 transition">
            Voltar para mesas
        </a>
    </div>

    <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-6 flex flex-col sm:flex-row items-center gap-6">
        <div class="bg-white rounded-xl p-3 shrink-0 [&>svg]:w-64 [&>svg]:h-64">
            {!! $svg !!}
        </div>

        <div class="space-y-4 w-full">
            <div>
                <p class="text-xs font-medium text-zinc-500 mb-1">Link da mesa</p>
                <p class="text-sm text-zinc-200 break-all font-mono">{{ $url }}</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.dining.tables.qr.download', [$table->id, 'png']) }}" download
                   class="text-sm text-white bg-orange-500 hover:bg-orange-600 rounded-lg px-3 py-1.5 transition">
                    Baixar PNG
                </a>
                <a href="{{ route('admin.dining.tables.qr.download', [$table->id, 'svg']) }}" download
                   class="text-sm text-zinc-300 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 rounded-lg px-3 py-1.5 transition">
                    Baixar SVG
                </a>
                <a href="{{ route('admin.dining.tables.qr.print') }}" target="_blank"
                   class="text-sm text-zinc-300 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 rounded-lg px-3 py-1.5 transition">
                    Imprimir todas as mesas
                </a>
            </div>
        </div>
    </div>
</x-layouts.app>

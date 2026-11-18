@props(['table'])

{{-- Row actions of Admin › Salão › Mesas; wire:click targets the parent dining.tables component. --}}
<div class="flex items-center justify-end gap-1">
    {{-- Status toggle — an open session (active tab) can only be cleared via "Fechar mesa" --}}
    @if($table->openSession)
    <button wire:click="closeSession({{ $table->id }})"
            wire:confirm="Fechar a mesa {{ $table->number }}? Isso encerra a conta e libera a mesa."
            class="text-xs text-teal-400 bg-teal-400/10 hover:bg-teal-400/20 rounded-lg px-2.5 py-1.5 transition">
        Fechar mesa
    </button>
    @else
    @if($table->status !== \App\Enums\DiningTableStatus::Free)
    <button wire:click="setStatus({{ $table->id }}, 'free')"
            class="text-xs text-green-400 bg-green-400/10 hover:bg-green-400/20 rounded-lg px-2.5 py-1.5 transition">
        Liberar
    </button>
    @endif
    @if($table->status !== \App\Enums\DiningTableStatus::Occupied)
    <button wire:click="setStatus({{ $table->id }}, 'occupied')"
            class="text-xs text-orange-400 bg-orange-400/10 hover:bg-orange-400/20 rounded-lg px-2.5 py-1.5 transition">
        Ocupar
    </button>
    @endif
    @if($table->status !== \App\Enums\DiningTableStatus::Reserved)
    <button wire:click="setStatus({{ $table->id }}, 'reserved')"
            class="text-xs text-blue-400 bg-blue-400/10 hover:bg-blue-400/20 rounded-lg px-2.5 py-1.5 transition">
        Reservar
    </button>
    @endif
    @endif
    <a href="{{ route('admin.dining.tables.qr', $table->id) }}" wire:navigate
       class="text-xs text-zinc-400 hover:text-white bg-zinc-800 hover:bg-zinc-700 rounded-lg px-2.5 py-1.5 transition">
        QR Code
    </a>
    <button wire:click="openEdit({{ $table->id }})"
            class="text-xs text-zinc-400 hover:text-white bg-zinc-800 hover:bg-zinc-700 rounded-lg px-2.5 py-1.5 transition">
        Editar
    </button>
    <button wire:click="delete({{ $table->id }})"
            wire:confirm="Excluir a mesa {{ $table->number }}?"
            class="text-xs text-red-400/70 hover:text-red-400 bg-zinc-800 hover:bg-red-400/10 rounded-lg px-2.5 py-1.5 transition">
        Excluir
    </button>
</div>

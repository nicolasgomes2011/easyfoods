{{--
    Start of an item line on the kitchen card: with `checklist`, a 48px box that ticks the
    item off (wired to the kitchen component: toggleItemPrepared); otherwise a small dot.
--}}
@props(['item', 'checklist' => false, 'dotClass' => 'bg-zinc-600'])

@if($checklist)
@php $prepared = $item->isPrepared(); @endphp
<button type="button"
        data-item-check="{{ $item->id }}"
        wire:click="toggleItemPrepared({{ $item->id }})"
        wire:loading.attr="disabled"
        wire:target="toggleItemPrepared({{ $item->id }})"
        aria-pressed="{{ $prepared ? 'true' : 'false' }}"
        aria-label="{{ $prepared ? 'Desmarcar' : 'Marcar como feito' }}: {{ $item->displayName() }}"
        class="size-12 shrink-0 inline-flex items-center justify-center rounded-xl border-2 transition {{ $prepared ? 'border-green-500 bg-green-500/20 text-green-300' : 'border-zinc-600 bg-zinc-800 text-transparent hover:border-zinc-400' }}">
    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
    </svg>
</button>
@else
<span class="size-2 rounded-full shrink-0 {{ $dotClass }}"></span>
@endif

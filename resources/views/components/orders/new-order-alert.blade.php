@props(['count' => 0])

{{-- New-order alert (pairs with App\Livewire\Concerns\AlertsNewOrders): on `order-alerts` it keeps
     "(n)" in the tab title and, when the poll reports fresh orders, plays a short beep made with
     the Web Audio API (no audio file). Sound starts off: browsers block audio until the user
     interacts, so "Ativar som" is that interaction; the choice is kept in localStorage. --}}
<div wire:ignore data-order-alert
     x-data="{
        base: document.title.replace(/^\(\d+\)\s*/, ''),
        sound: false,
        ctx: null,
        init() {
            try { this.sound = localStorage.getItem('easyfoods.orderAlertSound') === 'on'; } catch (e) {}
            this.alert({ count: {{ (int) $count }}, fresh: 0 });
        },
        toggle() {
            this.sound = ! this.sound;
            try { localStorage.setItem('easyfoods.orderAlertSound', this.sound ? 'on' : 'off'); } catch (e) {}
            this.beep();
        },
        beep() {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (! this.sound || ! AudioCtx) return;
            this.ctx ??= new AudioCtx();
            this.ctx.resume();
            [[880, 0], [1175, 0.18]].forEach(([freq, at]) => {
                const osc = this.ctx.createOscillator(), gain = this.ctx.createGain(), t = this.ctx.currentTime + at;
                osc.frequency.value = freq;
                gain.gain.setValueAtTime(0.25, t);
                gain.gain.exponentialRampToValueAtTime(0.001, t + 0.15);
                osc.connect(gain).connect(this.ctx.destination);
                osc.start(t);
                osc.stop(t + 0.16);
            });
        },
        alert({ count, fresh }) {
            document.title = (count ? '(' + count + ') ' : '') + this.base;
            if (fresh) this.beep();
        },
     }"
     @order-alerts.window="alert($event.detail)">
    <button type="button" @click="toggle()" :aria-pressed="sound"
            class="inline-flex items-center gap-1.5 text-xs text-zinc-300 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 rounded-lg px-3 py-1.5 transition">
        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg>
        <span x-text="sound ? 'Silenciar' : 'Ativar som'">Ativar som</span>
    </button>
</div>

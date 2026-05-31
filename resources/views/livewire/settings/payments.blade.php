<?php
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Models\Restaurant;
use App\Models\StoreSetting;

new #[Layout('components.layouts.app')] class extends Component {

    public bool $cash         = false;
    public bool $pix          = false;
    public bool $creditCard   = false;
    public bool $debitCard    = false;
    public bool $mealVoucher  = false;

    public bool $saved = false;

    private array $methodKeys = [
        'cash'        => 'payment_cash',
        'pix'         => 'payment_pix',
        'creditCard'  => 'payment_credit_card',
        'debitCard'   => 'payment_debit_card',
        'mealVoucher' => 'payment_meal_voucher',
    ];

    public function mount(): void
    {
        $restaurant = Restaurant::first();
        if (! $restaurant) return;

        $settings = $restaurant->settings->keyBy('key');

        foreach ($this->methodKeys as $prop => $key) {
            $this->$prop = (bool) ($settings->get($key)?->castValue() ?? false);
        }
    }

    public function save(): void
    {
        $restaurant = Restaurant::first();
        if (! $restaurant) return;

        foreach ($this->methodKeys as $prop => $key) {
            StoreSetting::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'key' => $key],
                ['value' => $this->$prop ? '1' : '0', 'type' => 'boolean']
            );
        }

        $this->saved = true;
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Formas de Pagamento</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Configure quais métodos de pagamento o restaurante aceita</p>
        </div>
        @if($saved)
        <span class="text-sm text-green-400 bg-green-400/10 border border-green-400/20 rounded-lg px-3 py-1.5">
            Salvo
        </span>
        @endif
    </div>

    @if(! \App\Models\Restaurant::exists())
    <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-5 py-10 text-center">
        <p class="text-zinc-400 text-sm">Configure as informações do restaurante antes de definir os pagamentos.</p>
        <a href="{{ route('admin.settings.store') }}" wire:navigate
           class="inline-block mt-3 text-sm text-orange-400 hover:text-orange-300 transition">
            Ir para Configurações da Loja →
        </a>
    </div>
    @else
    <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-5 space-y-1">
        @foreach([
            ['prop' => 'cash',        'label' => 'Dinheiro',          'desc' => 'Pagamento em espécie no momento da entrega ou retirada'],
            ['prop' => 'pix',         'label' => 'Pix',               'desc' => 'Pagamento instantâneo via chave Pix'],
            ['prop' => 'creditCard',  'label' => 'Cartão de crédito', 'desc' => 'Visa, Mastercard, Elo e outras bandeiras'],
            ['prop' => 'debitCard',   'label' => 'Cartão de débito',  'desc' => 'Débito na entrega ou retirada'],
            ['prop' => 'mealVoucher', 'label' => 'Vale-refeição',     'desc' => 'Alelo, Ticket, VR e similares'],
        ] as $method)
        <label class="flex items-center gap-4 py-3 px-3 rounded-xl hover:bg-zinc-800/50 cursor-pointer select-none transition group">
            <input type="checkbox" wire:model="{{$method['prop']}}"
                   class="rounded border-zinc-600 bg-zinc-800 text-orange-500 focus:ring-orange-500 shrink-0" />
            <div class="flex-1">
                <p class="text-sm font-medium text-zinc-200 group-hover:text-white transition">{{ $method['label'] }}</p>
                <p class="text-xs text-zinc-500">{{ $method['desc'] }}</p>
            </div>
            @if($this->{$method['prop']})
            <span class="text-xs text-green-400 bg-green-400/10 rounded-full px-2 py-0.5 shrink-0">Ativo</span>
            @endif
        </label>
        @endforeach
    </div>

    <div class="flex justify-end mt-4">
        <button wire:click="save" wire:loading.attr="disabled"
                class="text-sm text-white bg-orange-500 hover:bg-orange-600 rounded-xl px-5 py-2.5 font-medium transition">
            <span wire:loading.remove wire:target="save">Salvar preferências</span>
            <span wire:loading wire:target="save">Salvando…</span>
        </button>
    </div>
    @endif
</div>

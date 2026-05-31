<?php
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use App\Models\Restaurant;
use App\Models\OperatingHour;

new #[Layout('components.layouts.app')] class extends Component {

    public array $hours = [];
    public bool  $saved = false;

    public function mount(): void
    {
        $restaurant = Restaurant::first();

        if (! $restaurant) {
            return;
        }

        $existing = $restaurant->operatingHours->keyBy('weekday');

        for ($day = 0; $day <= 6; $day++) {
            $row = $existing->get($day);
            $this->hours[$day] = [
                'opens_at'  => $row?->opens_at  ?? '09:00',
                'closes_at' => $row?->closes_at ?? '22:00',
                'is_closed' => $row?->is_closed ?? ($day === 0),
            ];
        }
    }

    public function weekdayName(int $day): string
    {
        return match($day) {
            0 => 'Domingo',
            1 => 'Segunda-feira',
            2 => 'Terça-feira',
            3 => 'Quarta-feira',
            4 => 'Quinta-feira',
            5 => 'Sexta-feira',
            6 => 'Sábado',
        };
    }

    public function rules(): array
    {
        $rules = [];
        for ($day = 0; $day <= 6; $day++) {
            $rules["hours.{$day}.opens_at"]  = 'required_unless:hours.' . $day . '.is_closed,true|nullable|date_format:H:i';
            $rules["hours.{$day}.closes_at"] = 'required_unless:hours.' . $day . '.is_closed,true|nullable|date_format:H:i';
            $rules["hours.{$day}.is_closed"] = 'boolean';
        }
        return $rules;
    }

    public function save(): void
    {
        $restaurant = Restaurant::first();

        if (! $restaurant) {
            return;
        }

        $this->validate();

        for ($day = 0; $day <= 6; $day++) {
            OperatingHour::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'weekday' => $day],
                [
                    'opens_at'  => $this->hours[$day]['is_closed'] ? null : $this->hours[$day]['opens_at'],
                    'closes_at' => $this->hours[$day]['is_closed'] ? null : $this->hours[$day]['closes_at'],
                    'is_closed' => $this->hours[$day]['is_closed'],
                ]
            );
        }

        $this->saved = true;
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Horários de Funcionamento</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Configure o horário de abertura e fechamento por dia da semana</p>
        </div>
        @if($saved)
        <span class="text-sm text-green-400 bg-green-400/10 border border-green-400/20 rounded-lg px-3 py-1.5">
            Salvo
        </span>
        @endif
    </div>

    @if(! \App\Models\Restaurant::exists())
    <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-5 py-10 text-center">
        <p class="text-zinc-400 text-sm">Configure as informações do restaurante antes de definir os horários.</p>
        <a href="{{ route('admin.settings.store') }}" wire:navigate
           class="inline-block mt-3 text-sm text-orange-400 hover:text-orange-300 transition">
            Ir para Configurações da Loja →
        </a>
    </div>
    @else
    <form wire:submit="save">
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden">
            <div class="divide-y divide-zinc-800">
                @for($day = 0; $day <= 6; $day++)
                <div class="flex items-center gap-4 px-5 py-4 {{ $this->hours[$day]['is_closed'] ? 'opacity-60' : '' }}">
                    <div class="w-32 shrink-0">
                        <p class="text-sm text-zinc-200 font-medium">{{ $this->weekdayName($day) }}</p>
                    </div>

                    <label class="flex items-center gap-2 shrink-0 cursor-pointer select-none">
                        <input type="checkbox" wire:model="hours.{{ $day }}.is_closed"
                               class="rounded border-zinc-600 bg-zinc-800 text-orange-500 focus:ring-orange-500" />
                        <span class="text-xs text-zinc-500">Fechado</span>
                    </label>

                    <div class="flex items-center gap-2 flex-1">
                        <input
                            wire:model="hours.{{ $day }}.opens_at"
                            type="time"
                            @if($this->hours[$day]['is_closed']) disabled @endif
                            class="bg-zinc-800 border border-zinc-700 text-white text-sm rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-500 disabled:opacity-40"
                        />
                        <span class="text-zinc-600 text-sm">até</span>
                        <input
                            wire:model="hours.{{ $day }}.closes_at"
                            type="time"
                            @if($this->hours[$day]['is_closed']) disabled @endif
                            class="bg-zinc-800 border border-zinc-700 text-white text-sm rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-500 disabled:opacity-40"
                        />
                    </div>

                    @error("hours.{$day}.opens_at")
                    <p class="text-red-400 text-xs">{{ $message }}</p>
                    @enderror
                </div>
                @endfor
            </div>
        </div>

        <div class="flex justify-end mt-4">
            <button type="submit" wire:loading.attr="disabled"
                    class="text-sm text-white bg-orange-500 hover:bg-orange-600 rounded-xl px-5 py-2.5 font-medium transition">
                <span wire:loading.remove wire:target="save">Salvar horários</span>
                <span wire:loading wire:target="save">Salvando…</span>
            </button>
        </div>
    </form>
    @endif
</div>

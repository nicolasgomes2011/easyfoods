<?php
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use App\Actions\Users\InviteStaffMember;
use App\Enums\UserRole;
use App\Models\User;

new #[Layout('components.layouts.app')] class extends Component {

    public bool   $showInvite  = false;
    public string $inviteName  = '';
    public string $inviteEmail = '';
    public string $inviteRole  = '';

    public ?string $inviteSuccess = null;
    public ?string $inviteError   = null;

    public function rules(): array
    {
        return [
            'inviteName'  => 'required|string|max:100',
            'inviteEmail' => 'required|email|unique:users,email',
            'inviteRole'  => ['required', \Illuminate\Validation\Rule::in(
                array_column(UserRole::cases(), 'value')
            )],
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'inviteName'  => 'nome',
            'inviteEmail' => 'e-mail',
            'inviteRole'  => 'função',
        ];
    }

    #[Computed]
    public function staff()
    {
        return User::orderBy('name')->get();
    }

    #[Computed]
    public function roles(): array
    {
        return UserRole::cases();
    }

    public function openInvite(): void
    {
        $this->reset('inviteName', 'inviteEmail', 'inviteRole', 'inviteSuccess', 'inviteError');
        $this->showInvite = true;
    }

    public function invite(): void
    {
        $this->validate();

        try {
            $result = app(InviteStaffMember::class)->handle(
                name:  $this->inviteName,
                email: $this->inviteEmail,
                role:  UserRole::from($this->inviteRole),
            );

            $this->inviteSuccess = $result['mail_sent']
                ? "Convite enviado para {$this->inviteEmail}."
                : "Conta criada para {$this->inviteEmail}. E-mail não configurado — envie o link de redefinição de senha manualmente.";

            $this->reset('inviteName', 'inviteEmail', 'inviteRole');
            unset($this->staff);
        } catch (\Throwable $e) {
            $this->inviteError = 'Erro ao criar funcionário: ' . $e->getMessage();
        }
    }

    public function cancelInvite(): void
    {
        $this->showInvite = false;
        $this->reset('inviteName', 'inviteEmail', 'inviteRole', 'inviteSuccess', 'inviteError');
    }

    public function toggleActive(User $user): void
    {
        if ($user->id === auth()->id()) {
            return;
        }

        $user->update(['is_active' => ! $user->is_active]);
        unset($this->staff);
    }

    public function updateRole(User $user, string $role): void
    {
        if ($user->id === auth()->id()) {
            return;
        }

        $user->update(['role' => UserRole::from($role)]);
        unset($this->staff);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Equipe</h1>
            <p class="text-sm text-zinc-400 mt-0.5">{{ $this->staff->count() }} funcionário(s) cadastrado(s)</p>
        </div>
        <button wire:click="openInvite"
                class="text-sm text-white bg-orange-500 hover:bg-orange-600 rounded-lg px-3 py-1.5 transition">
            + Convidar funcionário
        </button>
    </div>

    {{-- Invite modal --}}
    @if($showInvite)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" wire:click.self="cancelInvite">
        <div class="bg-zinc-900 border border-zinc-700 rounded-2xl w-full max-w-sm mx-4 p-6 shadow-xl">
            <h2 class="text-base font-semibold text-white mb-1">Convidar funcionário</h2>
            <p class="text-xs text-zinc-500 mb-5">Um link para definir a senha será enviado por e-mail.</p>

            @if($inviteSuccess)
            <div class="mb-4 px-4 py-3 bg-green-500/10 border border-green-500/20 rounded-xl text-sm text-green-400">
                {{ $inviteSuccess }}
            </div>
            @endif

            @if($inviteError)
            <div class="mb-4 px-4 py-3 bg-red-500/10 border border-red-500/20 rounded-xl text-sm text-red-400">
                {{ $inviteError }}
            </div>
            @endif

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5">Nome *</label>
                    <input wire:model="inviteName" type="text" placeholder="Nome completo"
                           class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                    @error('inviteName') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5">E-mail *</label>
                    <input wire:model="inviteEmail" type="email" placeholder="funcionario@email.com"
                           class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                    @error('inviteEmail') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5">Função *</label>
                    <select wire:model="inviteRole"
                            class="w-full bg-zinc-800 border border-zinc-700 text-zinc-300 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <option value="">Selecionar função…</option>
                        @foreach($this->roles as $role)
                        <option value="{{ $role->value }}">{{ $role->label() }}</option>
                        @endforeach
                    </select>
                    @error('inviteRole') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex gap-2 mt-6">
                <button wire:click="cancelInvite"
                        class="flex-1 text-sm text-zinc-300 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 rounded-xl px-4 py-2.5 transition">
                    {{ $inviteSuccess ? 'Fechar' : 'Cancelar' }}
                </button>
                @if(! $inviteSuccess)
                <button wire:click="invite" wire:loading.attr="disabled"
                        class="flex-1 text-sm text-white bg-orange-500 hover:bg-orange-600 rounded-xl px-4 py-2.5 transition font-medium">
                    <span wire:loading.remove wire:target="invite">Enviar convite</span>
                    <span wire:loading wire:target="invite">Enviando…</span>
                </button>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- Staff list --}}
    @if($this->staff->isEmpty())
    <div class="bg-zinc-900 border border-zinc-800 border-dashed rounded-xl px-5 py-16 text-center">
        <p class="text-zinc-500 text-sm mb-3">Nenhum funcionário cadastrado.</p>
        <button wire:click="openInvite" class="text-sm text-orange-400 hover:text-orange-300 transition">
            + Convidar primeiro funcionário
        </button>
    </div>
    @else
    <div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-zinc-800">
                    <th class="text-left px-5 py-3 text-xs font-medium text-zinc-500">Funcionário</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-zinc-500">Função</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-zinc-500">Status</th>
                    <th class="text-right px-5 py-3 text-xs font-medium text-zinc-500">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800/70">
                @foreach($this->staff as $member)
                @php $isSelf = $member->id === auth()->id(); @endphp
                <tr class="hover:bg-zinc-800/30 transition {{ ! $member->is_active ? 'opacity-60' : '' }}">
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-3">
                            <div class="size-8 rounded-full bg-zinc-700 flex items-center justify-center text-xs font-semibold text-zinc-300 shrink-0">
                                {{ $member->initials() }}
                            </div>
                            <div>
                                <p class="font-medium text-white">
                                    {{ $member->name }}
                                    @if($isSelf)
                                    <span class="text-xs text-zinc-600 ml-1">(você)</span>
                                    @endif
                                </p>
                                <p class="text-xs text-zinc-500">{{ $member->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3.5">
                        @if($isSelf)
                        <span class="text-sm text-zinc-300">{{ $member->role->label() }}</span>
                        @else
                        <select wire:change="updateRole({{ $member->id }}, $event.target.value)"
                                class="bg-zinc-800 border border-zinc-700 text-zinc-300 text-xs rounded-lg px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-orange-500">
                            @foreach($this->roles as $role)
                            <option value="{{ $role->value }}" @selected($member->role === $role)>
                                {{ $role->label() }}
                            </option>
                            @endforeach
                        </select>
                        @endif
                    </td>
                    <td class="px-4 py-3.5">
                        @if($member->is_active)
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-400/10 text-green-400">Ativo</span>
                        @else
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-zinc-700 text-zinc-400">Inativo</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        @if(! $isSelf)
                        <button wire:click="toggleActive({{ $member->id }})"
                                wire:confirm="{{ $member->is_active ? 'Desativar' : 'Reativar' }} a conta de {{ $member->name }}?"
                                class="text-xs {{ $member->is_active ? 'text-red-400/70 hover:text-red-400 hover:bg-red-400/10' : 'text-green-400/70 hover:text-green-400 hover:bg-green-400/10' }} bg-zinc-800 rounded-lg px-2.5 py-1.5 transition">
                            {{ $member->is_active ? 'Desativar' : 'Reativar' }}
                        </button>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

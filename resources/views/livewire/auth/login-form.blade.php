<div class="w-full max-w-sm">

    {{-- Logo / Brand --}}
    <div class="mb-8 text-center">
        <div class="inline-flex items-center justify-center size-14 rounded-2xl bg-orange-500 mb-4 shadow-lg shadow-orange-500/30">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-white tracking-tight">EasyFoods</h1>
        <p class="mt-1 text-sm text-zinc-500">Painel de gerenciamento</p>
    </div>

    {{-- Card --}}
    <div class="bg-zinc-900 border border-zinc-800 rounded-2xl p-8 shadow-2xl">

        <h2 class="text-base font-semibold text-white mb-6">Entrar na conta</h2>

        <form wire:submit="login" class="space-y-5">

            {{-- Email --}}
            <div>
                <label for="email" class="block text-sm font-medium text-zinc-300 mb-1.5">
                    Email
                </label>
                <input
                    id="email"
                    type="email"
                    wire:model="email"
                    class="w-full px-3.5 py-2.5 rounded-lg bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition"
                    placeholder="seu@email.com"
                    required
                    autofocus
                    autocomplete="email"
                >
                @error('email')
                    <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- Password --}}
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label for="password" class="block text-sm font-medium text-zinc-300">
                        Senha
                    </label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="text-xs text-orange-400 hover:text-orange-300 transition">
                            Esqueci a senha
                        </a>
                    @endif
                </div>
                <div x-data="{ show: false }" class="relative">
                    <input
                        id="password"
                        :type="show ? 'text' : 'password'"
                        wire:model="password"
                        class="w-full px-3.5 py-2.5 pr-10 rounded-lg bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition"
                        placeholder="••••••••"
                        required
                        autocomplete="current-password"
                    >
                    <button
                        type="button"
                        @click="show = !show"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-zinc-500 hover:text-zinc-300 transition"
                        tabindex="-1"
                    >
                        <svg x-show="!show" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg x-show="show" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display:none">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/>
                        </svg>
                    </button>
                </div>
                @error('password')
                    <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- Remember me --}}
            <label class="flex items-center gap-2.5 cursor-pointer select-none">
                <input
                    type="checkbox"
                    wire:model="remember"
                    class="rounded border-zinc-600 bg-zinc-800 text-orange-500 focus:ring-orange-500 focus:ring-offset-zinc-900 size-4"
                >
                <span class="text-sm text-zinc-400">Lembrar-me por 30 dias</span>
            </label>

            {{-- Submit --}}
            <button
                type="submit"
                wire:loading.attr="disabled"
                class="w-full py-2.5 px-4 bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white font-semibold text-sm rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 focus:ring-offset-zinc-900 disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2"
            >
                <svg wire:loading class="animate-spin size-4 shrink-0" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <span wire:loading.remove>Entrar</span>
                <span wire:loading>Entrando...</span>
            </button>

        </form>
    </div>

    <p class="mt-6 text-center text-xs text-zinc-600">
        &copy; {{ date('Y') }} EasyFoods &mdash; Todos os direitos reservados.
    </p>

</div>

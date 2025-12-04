<x-layouts.guest>
    <div class="max-w-md mx-auto py-12 w-full">
        <div class="card p-6">
            <h2 class="text-xl font-semibold mb-4">Entrar</h2>

            <form wire:submit.prevent="login" autocomplete="off">
                <!-- email -->
                <div class="mb-3">
                    <label for="email" class="block text-sm">Email</label>
                    <input id="email" type="email"
                           wire:model.lazy="email"
                           class="form-control mt-1"
                           required
                           autofocus>
                    @error('email') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <!-- password -->
                <div class="mb-3">
                    <label for="password" class="block text-sm">Senha</label>
                    <input id="password" type="password"
                           wire:model.lazy="password"
                           class="form-control mt-1"
                           required>
                    @error('password') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <!-- remember -->
                <div class="mb-4">
                    <label class="inline-flex items-center">
                        <input type="checkbox" wire:model="remember" class="mr-2">
                        <span class="text-sm">Lembrar-me</span>
                    </label>
                </div>

                <!-- submit -->
                <div class="flex items-center gap-2">
                    <button type="submit"
                            class="btn btn-primary"
                            wire:loading.attr="disabled">
                        <span wire:loading.remove>Entrar</span>
                        <span wire:loading>Processando...</span>
                    </button>

                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="text-sm ml-auto">Esqueci a senha</a>
                    @endif
                </div>
            </form>
        </div>
    </div>
</x-layouts.guest>

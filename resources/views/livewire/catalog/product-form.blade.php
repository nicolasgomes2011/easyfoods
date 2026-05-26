<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">
                {{ $product ? 'Editar produto' : 'Novo produto' }}
            </h1>
            <p class="text-sm text-zinc-400 mt-0.5">
                {{ $product ? 'Atualize as informações do produto' : 'Preencha os dados para adicionar ao cardápio' }}
            </p>
        </div>
        <a href="{{ route('admin.catalog.products') }}" wire:navigate
           class="text-sm text-zinc-400 hover:text-white transition">
            ← Voltar
        </a>
    </div>

    <form wire:submit="save">
        <div class="grid grid-cols-3 gap-6 mb-6">

            {{-- Coluna principal --}}
            <div class="col-span-2 bg-zinc-900 border border-zinc-800 rounded-xl p-5 space-y-5">
                <p class="text-xs font-medium text-zinc-500 uppercase tracking-wider">Informações básicas</p>

                <div>
                    <label class="block text-sm text-zinc-300 mb-1.5">
                        Nome <span class="text-red-400">*</span>
                    </label>
                    <input
                        wire:model.live="name"
                        type="text"
                        placeholder="Ex: X-Burguer Clássico"
                        class="w-full bg-zinc-800 border border-zinc-700 rounded-lg px-3 py-2 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-orange-500 transition"
                    />
                    @error('name')
                        <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm text-zinc-300 mb-1.5">Descrição</label>
                    <textarea
                        wire:model="description"
                        rows="4"
                        placeholder="Descreva os ingredientes e diferenciais do produto..."
                        class="w-full bg-zinc-800 border border-zinc-700 rounded-lg px-3 py-2 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-orange-500 transition resize-none"
                    ></textarea>
                    @error('description')
                        <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Coluna lateral --}}
            <div class="space-y-4">
                <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-5 space-y-5">
                    <p class="text-xs font-medium text-zinc-500 uppercase tracking-wider">Configurações</p>

                    <div x-data="{ adding: false }">
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-sm text-zinc-300">
                                Categoria <span class="text-red-400">*</span>
                            </label>
                            <button
                                type="button"
                                x-show="!adding"
                                x-on:click="adding = true"
                                x-on:category-created.window="adding = false"
                                class="text-xs text-orange-400 hover:text-orange-300 transition"
                            >+ Nova categoria</button>
                        </div>

                        <select
                            x-show="!adding"
                            wire:model="category_id"
                            class="w-full bg-zinc-800 border border-zinc-700 rounded-lg px-3 py-2 text-sm text-zinc-300 focus:outline-none focus:border-orange-500 transition"
                        >
                            <option value="">Selecione...</option>
                            @foreach($this->categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>

                        <div x-show="adding" x-cloak class="flex gap-2">
                            <input
                                wire:model="newCategoryName"
                                x-ref="newCatInput"
                                x-on:category-created.window="adding = false"
                                x-init="$watch('adding', v => v && $nextTick(() => $refs.newCatInput.focus()))"
                                type="text"
                                placeholder="Nome da categoria"
                                class="flex-1 bg-zinc-800 border border-zinc-700 rounded-lg px-3 py-2 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-orange-500 transition"
                            />
                            <button
                                type="button"
                                wire:click="createCategory"
                                class="px-3 py-2 text-sm text-white bg-orange-500 hover:bg-orange-600 rounded-lg transition"
                            >Criar</button>
                            <button
                                type="button"
                                x-on:click="adding = false; $wire.set('newCategoryName', '')"
                                class="px-3 py-2 text-sm text-zinc-400 hover:text-white bg-zinc-800 hover:bg-zinc-700 rounded-lg transition"
                            >✕</button>
                        </div>

                        @error('newCategoryName')
                            <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
                        @enderror
                        @error('category_id')
                            <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm text-zinc-300 mb-1.5">
                            Preço (R$) <span class="text-red-400">*</span>
                        </label>
                        <input
                            wire:model="price"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            class="w-full bg-zinc-800 border border-zinc-700 rounded-lg px-3 py-2 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-orange-500 transition"
                        />
                        @error('price')
                            <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm text-zinc-300 mb-1.5">Status</label>
                        <select
                            wire:model="availability_status"
                            class="w-full bg-zinc-800 border border-zinc-700 rounded-lg px-3 py-2 text-sm text-zinc-300 focus:outline-none focus:border-orange-500 transition"
                        >
                            @foreach($this->statuses() as $status)
                                <option value="{{ $status->value }}">{{ $status->label() }}</option>
                            @endforeach
                        </select>
                        @error('availability_status')
                            <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm text-zinc-300 mb-1.5">Ordem de exibição</label>
                        <input
                            wire:model="sort_order"
                            type="number"
                            min="0"
                            class="w-full bg-zinc-800 border border-zinc-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-orange-500 transition"
                        />
                        @error('sort_order')
                            <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between pt-1">
                        <div>
                            <p class="text-sm text-zinc-300">Destaque</p>
                            <p class="text-xs text-zinc-500 mt-0.5">Exibir em destaque no cardápio</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input wire:model="is_featured" type="checkbox" class="sr-only peer" />
                            <div class="w-9 h-5 bg-zinc-700 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-orange-500"></div>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        {{-- Ações --}}
        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.catalog.products') }}" wire:navigate
               class="px-4 py-2 text-sm text-zinc-400 hover:text-white bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 rounded-lg transition">
                Cancelar
            </a>
            <button
                type="submit"
                class="px-5 py-2 text-sm font-medium text-white bg-orange-500 hover:bg-orange-600 rounded-lg transition"
            >
                {{ $product ? 'Salvar alterações' : 'Criar produto' }}
            </button>
        </div>
    </form>
</div>

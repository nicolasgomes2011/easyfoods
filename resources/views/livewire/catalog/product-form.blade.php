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
            <div class="col-span-2 space-y-6">
                <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-5 space-y-5">
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

                {{-- Variações (P/M/G) --}}
                <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-5">
                    <div class="flex items-start justify-between gap-3 mb-4">
                        <div>
                            <p class="text-xs font-medium text-zinc-500 uppercase tracking-wider">Variações</p>
                            <p class="text-xs text-zinc-500 mt-1">Tamanhos ou versões com preço próprio (ex.: P / M / G). Quando existem, o cliente escolhe uma ao pedir.</p>
                        </div>
                        <button type="button" wire:click="addVariant"
                                class="text-xs text-white bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 rounded-lg px-3 py-1.5 transition shrink-0">
                            + Variação
                        </button>
                    </div>

                    @if(empty($variants))
                    <p class="text-sm text-zinc-600">Sem variações — o produto usa apenas o preço base.</p>
                    @else
                    <div class="space-y-2">
                        @foreach($variants as $i => $variant)
                        <div class="grid grid-cols-12 gap-2" wire:key="variant-{{ $variant['key'] }}">
                            <div class="col-span-5">
                                <input wire:model="variants.{{ $i }}.name" type="text" placeholder="Nome (ex.: Pequeno)"
                                       class="w-full bg-zinc-800 border border-zinc-700 rounded-lg px-3 py-2 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-orange-500 transition" />
                                @error("variants.{$i}.name") <p class="text-xs text-red-400 mt-1">Informe o nome.</p> @enderror
                            </div>
                            <div class="col-span-3">
                                <input wire:model="variants.{{ $i }}.price" type="number" step="0.01" min="0" placeholder="Preço (R$)"
                                       class="w-full bg-zinc-800 border border-zinc-700 rounded-lg px-3 py-2 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-orange-500 transition" />
                                @error("variants.{$i}.price") <p class="text-xs text-red-400 mt-1">Preço inválido.</p> @enderror
                            </div>
                            <div class="col-span-3">
                                <select wire:model="variants.{{ $i }}.availability_status"
                                        class="w-full bg-zinc-800 border border-zinc-700 rounded-lg px-3 py-2 text-sm text-zinc-300 focus:outline-none focus:border-orange-500 transition">
                                    @foreach($this->statuses() as $status)
                                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-span-1 flex items-start justify-end">
                                <button type="button" wire:click="removeVariant({{ $i }})" title="Remover variação"
                                        class="text-zinc-600 hover:text-red-400 transition px-2 py-2 rounded-lg hover:bg-zinc-800">
                                    ✕
                                </button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
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

                {{-- Imagem --}}
                <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-5 space-y-3">
                    <p class="text-xs font-medium text-zinc-500 uppercase tracking-wider">Imagem</p>

                    {{-- isPreviewable() guard: a rejected non-image (e.g. PDF) would make temporaryUrl() throw mid-render --}}
                    @if($photo && $photo->isPreviewable())
                        <img src="{{ $photo->temporaryUrl() }}" alt="Pré-visualização"
                             class="w-full h-36 object-cover rounded-lg border border-zinc-800" />
                    @elseif($product?->image && ! $removeImage)
                        <img src="{{ asset('storage/'.$product->image) }}" alt="{{ $product->name }}"
                             class="w-full h-36 object-cover rounded-lg border border-zinc-800" />
                    @else
                        <div class="w-full h-36 flex items-center justify-center bg-zinc-800/60 border border-dashed border-zinc-700 rounded-lg">
                            <span class="text-xs text-zinc-500">Sem imagem</span>
                        </div>
                    @endif

                    <input type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp"
                           class="block w-full text-xs text-zinc-400 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-800 file:px-3 file:py-2 file:text-xs file:text-zinc-300 hover:file:bg-zinc-700 file:transition file:cursor-pointer" />
                    <div wire:loading wire:target="photo" class="text-xs text-orange-400">Enviando…</div>
                    @error('photo')
                        <p class="text-xs text-red-400">{{ $message }}</p>
                    @enderror
                    <p class="text-xs text-zinc-600">JPG, PNG ou WebP até 2MB.</p>

                    @if($product?->image)
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" wire:model.live="removeImage"
                               class="rounded border-zinc-600 bg-zinc-800 text-orange-500 focus:ring-orange-500" />
                        <span class="text-xs text-zinc-400">Remover imagem atual</span>
                    </label>
                    @endif
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

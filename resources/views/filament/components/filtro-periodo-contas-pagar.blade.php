<div class="px-4 pt-4 pb-2 flex flex-wrap items-end gap-3">

    {{-- Botões rápidos de período --}}
    <div class="flex flex-wrap gap-1">
        @foreach([
            'hoje'           => 'Hoje',
            'esta_semana'    => 'Esta semana',
            'este_mes'       => 'Este mês',
            'mes_passado'    => 'Mês passado',
            'selecionar_mes' => 'Selecionar mês',
            'dia_especifico' => 'Dia específico',
            'customizado'    => 'Período customizado',
        ] as $valor => $label)
            <button
                type="button"
                wire:click="$set('periodo', '{{ $valor }}')"
                class="px-3 py-1 rounded text-xs font-medium transition
                    {{ $this->periodo === $valor
                        ? 'bg-primary-600 text-white'
                        : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Dia específico --}}
    @if($this->periodo === 'dia_especifico')
        <div>
            <input
                type="date"
                wire:model.live="dia_selecionado"
                class="text-sm rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 px-2 py-1"
            />
        </div>
    @endif

    {{-- Selecionar mês --}}
    @if($this->periodo === 'selecionar_mes')
        <div>
            <select
                wire:model.live="mes_selecionado"
                class="text-sm rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 px-2 py-1"
            >
                @for($i = 0; $i < 24; $i++)
                    @php $d = now()->subMonths($i)->startOfMonth(); @endphp
                    <option value="{{ $d->format('Y-m') }}">
                        {{ ucfirst($d->locale('pt_BR')->isoFormat('MMMM [de] YYYY')) }}
                    </option>
                @endfor
            </select>
        </div>
    @endif

    {{-- Período customizado --}}
    @if($this->periodo === 'customizado')
        <div class="flex items-center gap-2">
            <input
                type="date"
                wire:model.live="data_inicio"
                class="text-sm rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 px-2 py-1"
            />
            <span class="text-gray-400 text-xs">até</span>
            <input
                type="date"
                wire:model.live="data_fim"
                class="text-sm rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 px-2 py-1"
            />
        </div>
    @endif

</div>

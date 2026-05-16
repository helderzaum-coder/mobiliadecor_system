<x-filament-panels::page>
    <form wire:submit.prevent="">
        {{ $this->form }}
    </form>

    {{-- Totais --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
        @if($this->exibir_saldo_anterior)
        <div class="rounded-xl border border-gray-700 bg-gray-800 p-4">
            <div class="text-xs text-gray-400 uppercase">Saldo Anterior</div>
            <div class="text-lg font-bold {{ $this->saldoAnterior >= 0 ? 'text-blue-400' : 'text-red-400' }}">
                R$ {{ number_format($this->saldoAnterior, 2, ',', '.') }}
            </div>
        </div>
        @endif
        <div class="rounded-xl border border-green-800 bg-green-900/20 p-4">
            <div class="text-xs text-gray-400 uppercase">Entradas</div>
            <div class="text-lg font-bold text-green-400">
                R$ {{ number_format($this->totais['entradas'], 2, ',', '.') }}
            </div>
        </div>
        <div class="rounded-xl border border-red-800 bg-red-900/20 p-4">
            <div class="text-xs text-gray-400 uppercase">Saídas</div>
            <div class="text-lg font-bold text-red-400">
                R$ {{ number_format($this->totais['saidas'], 2, ',', '.') }}
            </div>
        </div>
        <div class="rounded-xl border border-gray-700 bg-gray-800 p-4">
            <div class="text-xs text-gray-400 uppercase">{{ $this->exibir_saldo_anterior ? 'Saldo Final' : 'Resultado' }}</div>
            <div class="text-lg font-bold {{ ($this->exibir_saldo_anterior ? $this->totais['saldo_final'] : $this->totais['resultado']) >= 0 ? 'text-green-400' : 'text-red-400' }}">
                R$ {{ number_format($this->exibir_saldo_anterior ? $this->totais['saldo_final'] : $this->totais['resultado'], 2, ',', '.') }}
            </div>
        </div>
    </div>

    {{-- Movimentações --}}
    <div class="mt-6">
        @if($this->visao === 'diaria')
            {{-- Visão Diária --}}
            @forelse($this->movimentacoes as $dia)
                <div class="mb-4">
                    <div class="flex items-center justify-between bg-gray-800 rounded-t-lg px-4 py-2 border border-gray-700">
                        <span class="font-medium text-gray-200">
                            {{ \Carbon\Carbon::parse($dia['data'])->format('d/m/Y') }}
                            <span class="text-xs text-gray-500 ml-2">{{ \Carbon\Carbon::parse($dia['data'])->locale('pt_BR')->isoFormat('dddd') }}</span>
                        </span>
                        <div class="flex gap-4 text-sm">
                            <span class="text-green-400">+R$ {{ number_format($dia['entradas'], 2, ',', '.') }}</span>
                            <span class="text-red-400">-R$ {{ number_format($dia['saidas'], 2, ',', '.') }}</span>
                            @if($this->exibir_saldo_anterior)
                                <span class="font-medium {{ $dia['saldo_acumulado'] >= 0 ? 'text-blue-400' : 'text-red-400' }}">
                                    Saldo: R$ {{ number_format($dia['saldo_acumulado'], 2, ',', '.') }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <table class="w-full text-sm border border-gray-700 border-t-0">
                        <tbody>
                            @foreach($dia['itens'] as $item)
                                <tr class="border-b border-gray-800 hover:bg-gray-800/50">
                                    <td class="px-4 py-2 w-8">
                                        @if($item['tipo'] === 'entrada')
                                            <span class="text-green-400">▲</span>
                                        @else
                                            <span class="text-red-400">▼</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-gray-300">{{ $item['descricao'] }}</td>
                                    <td class="px-4 py-2 text-gray-500 text-xs">{{ $item['categoria'] }}</td>
                                    <td class="px-4 py-2 text-gray-500 text-xs">{{ $item['banco'] }}</td>
                                    <td class="px-4 py-2 text-right font-medium {{ $item['tipo'] === 'entrada' ? 'text-green-400' : 'text-red-400' }}">
                                        {{ $item['tipo'] === 'entrada' ? '+' : '-' }}R$ {{ number_format($item['valor'], 2, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @empty
                <div class="text-center text-gray-500 py-8">Nenhuma movimentação no período.</div>
            @endforelse
        @else
            {{-- Visão por Categoria --}}
            <table class="w-full text-sm border border-gray-700 rounded-lg overflow-hidden">
                <thead class="bg-gray-800">
                    <tr>
                        <th class="text-left px-4 py-3 text-gray-400">Categoria</th>
                        <th class="text-center px-4 py-3 text-gray-400">Qtd</th>
                        <th class="text-right px-4 py-3 text-green-400">Entradas</th>
                        <th class="text-right px-4 py-3 text-red-400">Saídas</th>
                        <th class="text-right px-4 py-3 text-gray-400">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->movimentacoes as $cat)
                        <tr class="border-b border-gray-800 hover:bg-gray-800/50">
                            <td class="px-4 py-3 font-medium text-gray-200">{{ $cat['categoria'] }}</td>
                            <td class="px-4 py-3 text-center text-gray-500">{{ $cat['qtd'] }}</td>
                            <td class="px-4 py-3 text-right text-green-400">
                                {{ $cat['entradas'] > 0 ? 'R$ ' . number_format($cat['entradas'], 2, ',', '.') : '-' }}
                            </td>
                            <td class="px-4 py-3 text-right text-red-400">
                                {{ $cat['saidas'] > 0 ? 'R$ ' . number_format($cat['saidas'], 2, ',', '.') : '-' }}
                            </td>
                            <td class="px-4 py-3 text-right font-medium {{ $cat['saldo'] >= 0 ? 'text-green-400' : 'text-red-400' }}">
                                R$ {{ number_format($cat['saldo'], 2, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-gray-500 py-8">Nenhuma movimentação no período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>
</x-filament-panels::page>

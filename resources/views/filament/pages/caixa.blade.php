<x-filament-panels::page>
    <form wire:submit.prevent="">
        {{ $this->form }}
    </form>

    {{-- Totais --}}
    <div class="grid grid-cols-1 md:grid-cols-{{ $this->exibir_saldo_anterior ? '4' : '3' }} gap-4 mt-4">
        @if($this->exibir_saldo_anterior)
        <div class="rounded-xl border border-cyan-800 p-4">
            <div class="text-xs text-cyan-500 uppercase font-semibold">Saldo Anterior</div>
            <div class="text-2xl font-bold text-white mt-1">
                R$ {{ number_format($this->saldoAnterior, 2, ',', '.') }}
            </div>
        </div>
        @endif
        <div class="rounded-xl border border-green-800 p-4">
            <div class="text-xs text-green-500 uppercase font-semibold">Entradas</div>
            <div class="text-2xl font-bold text-green-400 mt-1">
                R$ {{ number_format($this->totais['entradas'], 2, ',', '.') }}
            </div>
        </div>
        <div class="rounded-xl border border-red-800 p-4">
            <div class="text-xs text-red-500 uppercase font-semibold">Saídas</div>
            <div class="text-2xl font-bold text-red-400 mt-1">
                R$ {{ number_format($this->totais['saidas'], 2, ',', '.') }}
            </div>
        </div>
        <div class="rounded-xl border border-orange-800 p-4">
            <div class="text-xs text-orange-500 uppercase font-semibold">{{ $this->exibir_saldo_anterior ? 'Saldo Final' : 'Resultado' }}</div>
            <div class="text-2xl font-bold text-white mt-1">
                R$ {{ number_format($this->exibir_saldo_anterior ? $this->totais['saldo_final'] : $this->totais['resultado'], 2, ',', '.') }}
            </div>
        </div>
    </div>

    {{-- Movimentações --}}
    <div class="mt-6">
        @if($this->visao === 'diaria')
            @forelse($this->movimentacoes as $dia)
                <table class="w-full text-sm mb-4">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th colspan="4" class="text-left px-4 py-3">
                                <span class="text-white font-semibold">{{ \Carbon\Carbon::parse($dia['data'])->format('d/m/Y') }}</span>
                                <span class="text-gray-500 text-xs ml-2">{{ \Carbon\Carbon::parse($dia['data'])->locale('pt_BR')->isoFormat('dddd') }}</span>
                            </th>
                            <th class="text-right px-4 py-3 text-green-400 text-xs font-normal">+R$ {{ number_format($dia['entradas'], 2, ',', '.') }}</th>
                            <th class="text-right px-4 py-3 text-red-400 text-xs font-normal">-R$ {{ number_format($dia['saidas'], 2, ',', '.') }}</th>
                            @if($this->exibir_saldo_anterior)
                            <th class="text-right px-4 py-3 text-gray-400 text-xs font-normal">Saldo: R$ {{ number_format($dia['saldo_acumulado'], 2, ',', '.') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($dia['itens'] as $item)
                            <tr class="border-b border-gray-800/50">
                                <td class="px-4 py-2.5 w-10 text-center">
                                    @if($item['tipo'] === 'entrada')
                                        <span class="text-green-400 text-xs">▲</span>
                                    @else
                                        <span class="text-red-400 text-xs">▼</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-gray-200">{{ $item['descricao'] }}</td>
                                <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $item['categoria'] }}</td>
                                <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $item['banco'] !== '-' ? $item['banco'] : '' }}</td>
                                <td colspan="{{ $this->exibir_saldo_anterior ? '3' : '2' }}" class="px-4 py-2.5 text-right font-medium {{ $item['tipo'] === 'entrada' ? 'text-green-400' : 'text-red-400' }}">
                                    {{ $item['tipo'] === 'entrada' ? '+' : '-' }}R$ {{ number_format($item['valor'], 2, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @empty
                <div class="text-center text-gray-500 py-12">Nenhuma movimentação no período.</div>
            @endforelse
        @else
            {{-- Visão por Categoria --}}
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-700">
                        <th class="text-left px-4 py-3 text-gray-400 font-medium">Categoria</th>
                        <th class="text-center px-4 py-3 text-gray-400 font-medium">Qtd</th>
                        <th class="text-right px-4 py-3 text-gray-400 font-medium">Entradas</th>
                        <th class="text-right px-4 py-3 text-gray-400 font-medium">Saídas</th>
                        <th class="text-right px-4 py-3 text-gray-400 font-medium">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->movimentacoes as $cat)
                        <tr class="border-b border-gray-800/50">
                            <td class="px-4 py-3 text-gray-200 font-medium">{{ $cat['categoria'] }}</td>
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
                        <tr><td colspan="5" class="text-center text-gray-500 py-12">Nenhuma movimentação no período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>
</x-filament-panels::page>

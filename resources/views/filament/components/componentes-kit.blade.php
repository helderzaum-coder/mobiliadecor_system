<div class="space-y-2">
    @if($produto->componentes->isEmpty())
        <p class="text-sm text-gray-500">Nenhum componente vinculado.</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b">
                    <th class="text-left py-2">SKU</th>
                    <th class="text-left py-2">Nome</th>
                    <th class="text-center py-2">Qtd</th>
                    <th class="text-center py-2">Saldo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($produto->componentes as $comp)
                    <tr class="border-b">
                        <td class="py-2 font-mono">{{ $comp->sku }}</td>
                        <td class="py-2">{{ $comp->nome }}</td>
                        <td class="py-2 text-center">{{ $comp->pivot->quantidade }}</td>
                        <td class="py-2 text-center font-bold {{ $comp->saldo <= $comp->saldo_minimo ? 'text-red-600' : 'text-green-600' }}">
                            {{ $comp->saldo }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

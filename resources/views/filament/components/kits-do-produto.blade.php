<div class="space-y-2">
    @if($produto->kits->isEmpty())
        <p class="text-sm text-gray-500">Este produto não faz parte de nenhum kit.</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b">
                    <th class="text-left py-2">SKU do Kit</th>
                    <th class="text-left py-2">Nome do Kit</th>
                    <th class="text-center py-2">Qtd no Kit</th>
                </tr>
            </thead>
            <tbody>
                @foreach($produto->kits as $kit)
                    <tr class="border-b">
                        <td class="py-2 font-mono">{{ $kit->sku }}</td>
                        <td class="py-2">{{ $kit->nome }}</td>
                        <td class="py-2 text-center">{{ $kit->pivot->quantidade }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

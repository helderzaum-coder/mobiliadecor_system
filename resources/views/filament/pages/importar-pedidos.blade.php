<x-filament-panels::page>
    <form wire:submit="importar">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit" wire:loading.attr="disabled">
                <span wire:loading.remove>Importar Pedidos</span>
                <span wire:loading>Importando...</span>
            </x-filament::button>
        </div>
    </form>

    @if($resultado)
        <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Resultado</h3>

            <div class="grid grid-cols-3 gap-4 mb-4">
                <div class="text-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                    <div class="text-2xl font-bold text-green-600">{{ $resultado['importados'] }}</div>
                    <div class="text-sm text-gray-500">Importados</div>
                </div>
                <div class="text-center p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                    <div class="text-2xl font-bold text-yellow-600">{{ $resultado['ignorados'] }}</div>
                    <div class="text-sm text-gray-500">Já existentes</div>
                </div>
                <div class="text-center p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                    <div class="text-2xl font-bold text-red-600">{{ $resultado['erros'] }}</div>
                    <div class="text-sm text-gray-500">Erros</div>
                </div>
            </div>

            @if(!empty($resultado['mensagens']))
                <div class="mt-4">
                    <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-2">Mensagens:</h4>
                    <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400">
                        @foreach($resultado['mensagens'] as $msg)
                            <li>{{ $msg }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif
</x-filament-panels::page>

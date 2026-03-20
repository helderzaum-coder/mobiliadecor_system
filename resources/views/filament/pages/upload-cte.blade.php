<x-filament-panels::page>
    <div class="max-w-2xl">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700 mb-6">
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                Faça upload dos XMLs de CT-e. O sistema identifica duplicados automaticamente pela chave de acesso.
            </p>
            <p class="text-sm text-gray-400 dark:text-gray-500">
                Após o upload, use o botão "CT-e" na revisão de pedidos para vincular o frete.
            </p>
        </div>

        <form wire:submit="processar">
            {{ $this->form }}

            <div class="mt-4">
                <x-filament::button type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="processar">Enviar CT-es</span>
                    <span wire:loading wire:target="processar" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Processando...
                    </span>
                </x-filament::button>
            </div>
        </form>

        @if($resultado)
            <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Resultado</h3>

                <div class="grid grid-cols-4 gap-4 mb-4">
                    <div class="text-center p-4 bg-gray-50 dark:bg-gray-900/20 rounded-lg">
                        <div class="text-2xl font-bold text-gray-600 dark:text-gray-300">{{ $resultado['enviados'] }}</div>
                        <div class="text-sm text-gray-500">Enviados</div>
                    </div>
                    <div class="text-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                        <div class="text-2xl font-bold text-green-600">{{ $resultado['novos'] }}</div>
                        <div class="text-sm text-gray-500">Novos</div>
                    </div>
                    <div class="text-center p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                        <div class="text-2xl font-bold text-yellow-600">{{ $resultado['duplicados'] }}</div>
                        <div class="text-sm text-gray-500">Duplicados</div>
                    </div>
                    <div class="text-center p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                        <div class="text-2xl font-bold text-red-600">{{ $resultado['invalidos'] }}</div>
                        <div class="text-sm text-gray-500">Inválidos</div>
                    </div>
                </div>

                @if(!empty($resultado['detalhes']))
                    <div class="mt-4">
                        <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-2">Detalhes:</h4>
                        <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400">
                            @foreach($resultado['detalhes'] as $detalhe)
                                <li>{{ $detalhe }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>

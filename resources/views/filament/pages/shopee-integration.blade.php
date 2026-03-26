<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Status --}}
        <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Status da Conexão</h3>

            <div class="flex items-center gap-3 mb-4">
                @if($this->sandbox)
                    <span class="px-3 py-1 text-xs rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">
                        SANDBOX
                    </span>
                @else
                    <span class="px-3 py-1 text-xs rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400">
                        PRODUÇÃO
                    </span>
                @endif

                @if($this->authorized)
                    <span class="px-3 py-1 text-xs rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400">
                        ✓ Conectado (Shop #{{ $this->shopId }})
                    </span>
                @else
                    <span class="px-3 py-1 text-xs rounded-full bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400">
                        ✗ Não conectado
                    </span>
                @endif
            </div>

            <div class="flex gap-3">
                <x-filament::button wire:click="conectar" color="warning" icon="heroicon-o-arrow-top-right-on-square">
                    {{ $this->authorized ? 'Reconectar' : 'Conectarr Shopee' }}
                </x-filament::button>

                @if($this->authorized)
                    <x-filament::button wire:click="testConnection" color="info" icon="heroicon-o-signal">
                        Testar Conexão
                    </x-filament::button>
                @endif
            </div>
        </div>

        {{-- Instruções Sandbox --}}
        @if($this->sandbox && !$this->authorized)
            <div class="p-6 bg-amber-50 dark:bg-amber-900/10 rounded-xl border border-amber-200 dark:border-amber-700">
                <h4 class="font-medium text-amber-800 dark:text-amber-300 mb-2">Instruções Sandbox</h4>
                <ol class="list-decimal list-inside text-sm text-amber-700 dark:text-amber-400 space-y-1">
                    <li>Clique em "Conectar Shopee"</li>
                    <li>Na tela da Shopee, use as credenciais sandbox:</li>
                    <li class="ml-4">Account: <code class="bg-amber-100 dark:bg-amber-900/40 px-1 rounded">SANDBOX.9068f8deba6c9dcf6bab</code></li>
                    <li class="ml-4">Password: <code class="bg-amber-100 dark:bg-amber-900/40 px-1 rounded">f626052df9145b0a</code></li>
                    <li>Após autorizar, você será redirecionado de volta</li>
                </ol>
            </div>
        @endif

        @session('success')
            <div class="p-4 bg-green-50 dark:bg-green-900/10 rounded-xl border border-green-200 dark:border-green-700 text-green-700 dark:text-green-400 text-sm">
                {{ $value }}
            </div>
        @endsession

        @session('error')
            <div class="p-4 bg-red-50 dark:bg-red-900/10 rounded-xl border border-red-200 dark:border-red-700 text-red-700 dark:text-red-400 text-sm">
                {{ $value }}
            </div>
        @endsession
    </div>
</x-filament-panels::page>

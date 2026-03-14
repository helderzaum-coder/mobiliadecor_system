<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @foreach($this->getAccounts() as $key => $account)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $account['name'] }}
                    </h3>
                    @if($account['authorized'])
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                            Conectado
                        </span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                            Desconectado
                        </span>
                    @endif
                </div>

                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    Conta: <strong>{{ $key }}</strong>
                </p>

                <a href="{{ route('bling.authorize', $key) }}"
                   class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg
                          {{ $account['authorized']
                              ? 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                              : 'bg-primary-600 text-white hover:bg-primary-700' }}">
                    @if($account['authorized'])
                        Reconectar
                    @else
                        Autorizar
                    @endif
                </a>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>

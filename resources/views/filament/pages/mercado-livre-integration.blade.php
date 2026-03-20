<x-filament-panels::page>
    @if(session('success'))
        <div class="rounded-lg bg-green-50 dark:bg-green-900/20 p-4 mb-4">
            <p class="text-sm text-green-800 dark:text-green-200">{{ session('success') }}</p>
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-lg bg-red-50 dark:bg-red-900/20 p-4 mb-4">
            <p class="text-sm text-red-800 dark:text-red-200">{{ session('error') }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @foreach($this->getAccounts() as $key => $account)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $account['name'] }}
                    </h3>
                    @if($account['authorized'])
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                            ✅ Conectado
                        </span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                            ❌ Desconectado
                        </span>
                    @endif
                </div>

                <div class="text-sm text-gray-500 dark:text-gray-400 mb-4 space-y-1">
                    <p>Conta: <strong>{{ $key }}</strong></p>
                    @if($account['user_id'])
                        <p>User ID: <strong>{{ $account['user_id'] }}</strong></p>
                    @endif
                    @if($account['authorized'] && $account['expires_at'])
                        <p>Token expira: <strong>{{ $account['expires_at'] }}</strong></p>
                    @endif
                </div>

                <a href="{{ route('ml.authorize', $key) }}"
                   class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg
                          {{ $account['authorized']
                              ? 'bg-yellow-500 text-white hover:bg-yellow-600'
                              : 'bg-blue-600 text-white hover:bg-blue-700' }}">
                    @if($account['authorized'])
                        🔄 Reconectar
                    @else
                        🔗 Autorizar
                    @endif
                </a>
            </div>
        @endforeach
    </div>

    <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Instruções</h3>
        <ol class="list-decimal list-inside text-sm text-gray-600 dark:text-gray-400 space-y-2">
            <li>Clique em "Autorizar" na conta desejada</li>
            <li>Você será redirecionado para o Mercado Livre para fazer login</li>
            <li>Autorize o aplicativo e será redirecionado de volta</li>
            <li>O token é renovado automaticamente a cada 6 horas</li>
        </ol>
    </div>
</x-filament-panels::page>

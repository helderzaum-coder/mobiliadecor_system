<x-filament-panels::page>
    <div class="space-y-4">
        {{-- Instruções --}}
        <div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl text-sm">
            <p class="font-semibold text-blue-800 dark:text-blue-300 mb-2">📋 Como usar:</p>
            <ol class="list-decimal list-inside space-y-1 text-blue-700 dark:text-blue-400">
                <li>Acesse <a href="https://painel.frenet.com.br/Order/LabelCart" target="_blank" class="underline font-medium">painel.frenet.com.br/Order/LabelCart</a></li>
                <li>Filtre por <strong>Período</strong> desejado</li>
                <li>No filtro <strong>Status</strong>, selecione: <span class="font-medium">Aguardando postagem</span>, <span class="font-medium">Em trânsito</span>, <span class="font-medium">Entregue</span>, <span class="font-medium">Extraviado</span> e <span class="font-medium">Postado</span></li>
                <li>Selecione todo o texto da listagem (<kbd>Ctrl+A</kbd>) e copie (<kbd>Ctrl+C</kbd>)</li>
                <li>Cole no campo abaixo e clique em <strong>Converter</strong></li>
            </ol>
        </div>

        {{-- Textarea + Botões --}}
        <div x-data="conversorFrenet()">
            <textarea x-model="inputText" placeholder="Cole aqui o texto copiado do painel Frenet..."
                class="w-full h-48 p-4 font-mono text-sm rounded-xl border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-blue-500 focus:ring-0 resize-y"></textarea>

            <div class="flex flex-wrap gap-3 mt-3">
                <button @click="converter()" class="px-4 py-2 text-sm font-semibold rounded-lg bg-blue-600 text-white hover:bg-blue-700">
                    🔍 Converter
                </button>
                <button @click="limpar()" class="px-4 py-2 text-sm font-semibold rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600">
                    🗑️ Limpar
                </button>
                <button @click="exportarCSV()" x-show="registros.length > 0" class="px-4 py-2 text-sm font-semibold rounded-lg bg-green-600 text-white hover:bg-green-700">
                    📥 Exportar CSV
                </button>
                <span x-show="registros.length > 0" class="self-center text-sm text-gray-500 dark:text-gray-400" x-text="registros.length + ' registro(s)'"></span>
            </div>

            {{-- Tabela --}}
            <div x-show="registros.length > 0" class="mt-4 overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="w-full text-sm whitespace-nowrap">
                    <thead>
                        <tr class="bg-gray-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                            <th class="px-3 py-2 text-left font-semibold">ID</th>
                            <th class="px-3 py-2 text-left font-semibold">Data</th>
                            <th class="px-3 py-2 text-left font-semibold">Etiqueta</th>
                            <th class="px-3 py-2 text-left font-semibold">Destinatário</th>
                            <th class="px-3 py-2 text-left font-semibold">Cidade/UF</th>
                            <th class="px-3 py-2 text-left font-semibold">Modalidade</th>
                            <th class="px-3 py-2 text-left font-semibold">Preço</th>
                            <th class="px-3 py-2 text-left font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(r, i) in registros" :key="i">
                            <tr class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-3 py-2 font-mono" x-text="r.id"></td>
                                <td class="px-3 py-2" x-text="r.data"></td>
                                <td class="px-3 py-2 font-mono" x-text="r.etiqueta"></td>
                                <td class="px-3 py-2" x-text="r.destinatario"></td>
                                <td class="px-3 py-2" x-text="r.cidadeUf"></td>
                                <td class="px-3 py-2" x-text="r.modalidade"></td>
                                <td class="px-3 py-2" x-text="r.preco"></td>
                                <td class="px-3 py-2" x-text="r.status"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div x-show="registros.length === 0 && convertido" class="mt-4 p-6 text-center text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                Nenhum registro encontrado. Verifique se copiou o texto corretamente.
            </div>
        </div>
    </div>

    <script>
        function conversorFrenet() {
            return {
                inputText: '',
                registros: [],
                convertido: false,

                converter() {
                    this.convertido = true;
                    const blocos = this.inputText.split(/Carrier Image/gi);
                    this.registros = [];

                    for (let bloco of blocos) {
                        bloco = bloco.trim();
                        if (!bloco) continue;

                        const linhas = bloco.split('\n')
                            .map(l => l.trim())
                            .filter(l => l.length > 0 && l.toLowerCase() !== 'proteção' && l.toLowerCase() !== 'protecao');
                        if (linhas.length < 8) continue;
                        if (!linhas[0].toUpperCase().startsWith('ID:')) continue;

                        this.registros.push({
                            id: linhas[0].replace(/^ID:\s*/i, '').trim(),
                            data: linhas[1],
                            etiqueta: linhas[2],
                            destinatario: linhas[3],
                            cidadeUf: linhas[4],
                            modalidade: linhas[5],
                            preco: linhas[6],
                            status: linhas[7]
                        });
                    }
                },

                limpar() {
                    this.inputText = '';
                    this.registros = [];
                    this.convertido = false;
                },

                exportarCSV() {
                    if (!this.registros.length) return;
                    const colunas = ['ID', 'Data', 'Etiqueta', 'Destinatario', 'Cidade/UF', 'Modalidade', 'Preco', 'Status'];
                    let csv = colunas.join(';') + '\n';
                    this.registros.forEach(r => {
                        csv += [r.id, r.data, r.etiqueta, r.destinatario, r.cidadeUf, r.modalidade, r.preco, r.status]
                            .map(v => `"${v.replace(/"/g, '""')}"`)
                            .join(';') + '\n';
                    });

                    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = 'frenet_etiquetas_' + new Date().toISOString().slice(0,10) + '.csv';
                    link.click();
                }
            };
        }
    </script>
</x-filament-panels::page>

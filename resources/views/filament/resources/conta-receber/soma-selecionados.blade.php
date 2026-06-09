<tr x-data="{
    total: 0,
    qtd: 0,
    init() {
        this.calcular();
        document.addEventListener('click', () => setTimeout(() => this.calcular(), 150));
        document.addEventListener('change', () => setTimeout(() => this.calcular(), 150));
    },
    calcular() {
        let soma = 0;
        let count = 0;
        document.querySelectorAll('.fi-ta-row').forEach(row => {
            const cb = row.querySelector('input[type=checkbox]');
            if (cb && cb.checked) {
                const textos = row.querySelectorAll('td div');
                for (const div of textos) {
                    const t = div.textContent.trim();
                    if (t.startsWith('R$') && !div.closest('[class*=badge]')) {
                        const raw = t.replace('R$', '').trim();
                        // Formato do Filament money('BRL'): R$2,184.19 (virgula=milhar, ponto=decimal)
                        const v = parseFloat(raw.replace(/,/g, ''));
                        if (!isNaN(v) && v > 0) {
                            soma += v;
                            count++;
                            break;
                        }
                    }
                }
            }
        });
        this.total = soma;
        this.qtd = count;
    }
}" x-init="init()" class="bg-gray-50 dark:bg-white/5">
    <td colspan="20" class="px-4 py-2">
        <div x-show="qtd > 0" class="flex items-center gap-4 text-sm">
            <span class="text-gray-500 dark:text-gray-400">✓ Selecionados:</span>
            <span class="font-semibold text-primary-600 dark:text-primary-400" x-text="qtd + ' registro(s)'"></span>
            <span class="font-bold text-lg text-primary-600 dark:text-primary-400" x-text="'R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
        </div>
    </td>
</tr>

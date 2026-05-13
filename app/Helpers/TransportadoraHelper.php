<?php

namespace App\Helpers;

use App\Models\Transportadora;
use Illuminate\Support\Facades\Cache;

class TransportadoraHelper
{
    /**
     * Retorna mapa de alias (lowercase) → nome_transportadora.
     */
    public static function mapaAliases(): array
    {
        return Cache::remember('transportadora_aliases_map', 300, function () {
            $mapa = [];
            $transportadoras = Transportadora::where('ativo', true)->get();

            foreach ($transportadoras as $t) {
                $nome = $t->nome_transportadora;
                $mapa[mb_strtolower(trim($nome))] = $nome;

                foreach ($t->aliases ?? [] as $alias) {
                    $mapa[mb_strtolower(trim($alias))] = $nome;
                }
            }

            return $mapa;
        });
    }

    /**
     * Resolve o nome raw para o nome agrupado.
     * Comparação case-insensitive.
     */
    public static function resolver(?string $nomeRaw): ?string
    {
        if (!$nomeRaw) return null;

        $mapa = self::mapaAliases();
        $key = mb_strtolower(trim($nomeRaw));

        return $mapa[$key] ?? $nomeRaw;
    }

    /**
     * Retorna lista única de transportadoras para o filtro.
     */
    public static function listarUnicas(): array
    {
        $fromCte = \App\Models\Cte::whereNotNull('transportadora')
            ->where('transportadora', '!=', '')
            ->distinct()->pluck('transportadora');

        $fromStaging = \App\Models\PedidoBlingStaging::whereNotNull('transportadora')
            ->where('transportadora', '!=', '')
            ->distinct()->pluck('transportadora');

        $fromVenda = \App\Models\Venda::whereNotNull('transportadora_manual')
            ->where('transportadora_manual', '!=', '')
            ->distinct()->pluck('transportadora_manual');

        return $fromCte->merge($fromStaging)->merge($fromVenda)
            ->map(fn ($t) => self::resolver($t))
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn ($t) => [$t => $t])
            ->toArray();
    }

    /**
     * Limpa cache de aliases.
     */
    public static function limparCache(): void
    {
        Cache::forget('transportadora_aliases_map');
    }
}

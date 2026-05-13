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
                // O próprio nome mapeia para si
                $mapa[mb_strtolower(trim($nome))] = $nome;

                // Aliases explícitos
                foreach ($t->aliases ?? [] as $alias) {
                    $mapa[mb_strtolower(trim($alias))] = $nome;
                }
            }

            return $mapa;
        });
    }

    /**
     * Resolve o nome raw para o nome agrupado.
     * Tenta: 1) match exato no mapa, 2) match parcial (nome cadastrado contido no raw).
     */
    public static function resolver(?string $nomeRaw): ?string
    {
        if (!$nomeRaw) return null;

        $mapa = self::mapaAliases();
        $key = mb_strtolower(trim($nomeRaw));

        // Match exato
        if (isset($mapa[$key])) {
            return $mapa[$key];
        }

        // Match parcial: se o nome cadastrado está contido no raw
        $transportadoras = Cache::remember('transportadora_nomes_ativos', 300, function () {
            return Transportadora::where('ativo', true)->pluck('nome_transportadora')->toArray();
        });

        foreach ($transportadoras as $nome) {
            if (mb_stripos($key, mb_strtolower($nome)) !== false) {
                return $nome;
            }
        }

        return $nomeRaw;
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
     * Retorna todos os nomes raw (lowercase) que correspondem a um nome agrupado.
     * Inclui aliases explícitos + match parcial.
     */
    public static function nomesRawPara(string $nomeAgrupado): array
    {
        $mapa = self::mapaAliases();
        $nomes = [];

        // Aliases explícitos
        foreach ($mapa as $alias => $nome) {
            if ($nome === $nomeAgrupado) {
                $nomes[] = $alias;
            }
        }

        $nomes[] = mb_strtolower($nomeAgrupado);

        // Buscar nomes raw reais que fazem match parcial
        $allRaw = collect()
            ->merge(\App\Models\Cte::whereNotNull('transportadora')->where('transportadora', '!=', '')->distinct()->pluck('transportadora'))
            ->merge(\App\Models\PedidoBlingStaging::whereNotNull('transportadora')->where('transportadora', '!=', '')->distinct()->pluck('transportadora'))
            ->merge(\App\Models\Venda::whereNotNull('transportadora_manual')->where('transportadora_manual', '!=', '')->distinct()->pluck('transportadora_manual'));

        foreach ($allRaw as $raw) {
            if (self::resolver($raw) === $nomeAgrupado) {
                $nomes[] = mb_strtolower(trim($raw));
            }
        }

        return array_unique($nomes);
    }

    /**
     * Limpa cache de aliases.
     */
    public static function limparCache(): void
    {
        Cache::forget('transportadora_aliases_map');
        Cache::forget('transportadora_nomes_ativos');
    }
}

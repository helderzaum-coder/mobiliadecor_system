<?php

namespace App\Helpers;

use App\Models\Transportadora;
use Illuminate\Support\Facades\Cache;

class TransportadoraHelper
{
    /**
     * Retorna mapa de alias → nome_transportadora (cache 5min).
     * Ex: ["BRASIL WEB TRANSPORTES E LOGISTICA LTDA" => "Brasil Web", ...]
     */
    public static function mapaAliases(): array
    {
        return Cache::remember('transportadora_aliases_map', 300, function () {
            $mapa = [];
            $transportadoras = Transportadora::whereNotNull('aliases')->get();

            foreach ($transportadoras as $t) {
                $aliases = $t->aliases ?? [];
                foreach ($aliases as $alias) {
                    $mapa[mb_strtolower(trim($alias))] = $t->nome_transportadora;
                }
                // O próprio nome também mapeia para si
                $mapa[mb_strtolower(trim($t->nome_transportadora))] = $t->nome_transportadora;
            }

            return $mapa;
        });
    }

    /**
     * Resolve o nome raw para o nome agrupado da transportadora.
     * Se não encontrar alias, retorna o nome original.
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
     * Prioriza nomes cadastrados, agrupa aliases.
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

        $todos = $fromCte->merge($fromStaging)->merge($fromVenda)
            ->map(fn ($t) => self::resolver($t))
            ->filter()
            ->unique()
            ->sort();

        return $todos->mapWithKeys(fn ($t) => [$t => $t])->toArray();
    }

    /**
     * Retorna todos os nomes raw que correspondem a um nome agrupado.
     */
    public static function nomesRawPara(string $nomeAgrupado): array
    {
        $mapa = self::mapaAliases();
        $nomes = [];

        foreach ($mapa as $alias => $nome) {
            if ($nome === $nomeAgrupado) {
                $nomes[] = $alias;
            }
        }

        // Incluir o próprio nome agrupado
        $nomes[] = mb_strtolower($nomeAgrupado);

        return array_unique($nomes);
    }

    /**
     * Limpa cache de aliases (chamar ao editar transportadora).
     */
    public static function limparCache(): void
    {
        Cache::forget('transportadora_aliases_map');
    }
}

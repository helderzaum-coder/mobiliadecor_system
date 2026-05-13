<?php

namespace App\Helpers;

class TransportadoraHelper
{
    /**
     * Normaliza o nome da transportadora para agrupar variações.
     */
    public static function normalizar(?string $nome): ?string
    {
        if (!$nome) return null;

        // Uppercase e trim
        $nome = mb_strtoupper(trim($nome));

        // Remover sufixos jurídicos
        $nome = preg_replace('/\s*(LTDA|ME|EIRELI|EPP|S\.?A\.?|TRANSPORTES?|TRANSPORTE|E LOGISTICA|E LOGÍSTICA|LOGISTICA|LOGÍSTICA)\.?\s*$/i', '', $nome);
        $nome = preg_replace('/\s*(LTDA|ME|EIRELI|EPP)\.?\s*$/i', '', $nome); // segunda passada

        // Remover pontuação e espaços extras
        $nome = preg_replace('/[.\-\/]/', ' ', $nome);
        $nome = preg_replace('/\s+/', ' ', trim($nome));

        return $nome ?: null;
    }

    /**
     * Retorna lista única de transportadoras normalizadas a partir das 3 fontes.
     * Retorna [nome_normalizado => nome_normalizado]
     */
    public static function listarUnicas(): array
    {
        $fromCte = \App\Models\Cte::whereNotNull('transportadora')
            ->where('transportadora', '!=', '')
            ->distinct()
            ->pluck('transportadora');

        $fromStaging = \App\Models\PedidoBlingStaging::whereNotNull('transportadora')
            ->where('transportadora', '!=', '')
            ->distinct()
            ->pluck('transportadora');

        $fromVenda = \App\Models\Venda::whereNotNull('transportadora_manual')
            ->where('transportadora_manual', '!=', '')
            ->distinct()
            ->pluck('transportadora_manual');

        return $fromCte->merge($fromStaging)->merge($fromVenda)
            ->map(fn ($t) => self::normalizar($t))
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn ($t) => [$t => $t])
            ->toArray();
    }

    /**
     * Verifica se um nome de transportadora (raw) corresponde ao filtro normalizado.
     */
    public static function corresponde(?string $raw, string $filtro): bool
    {
        return self::normalizar($raw) === $filtro;
    }
}

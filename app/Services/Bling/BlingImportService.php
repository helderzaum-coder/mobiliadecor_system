<?php

namespace App\Services\Bling;

use App\Models\PedidoBlingStaging;

class BlingImportService
{
    public function __construct(string $accountKey) {}

    public function importarParaStaging(string $dataInicio, string $dataFim): array
    {
        return ['importados' => 0, 'ignorados' => 0, 'erros' => 0, 'mensagens' => ['Importação desativada']];
    }

    public function importarPedidoPorId(int $blingId): array
    {
        return ['status' => 'desativado', 'motivo' => 'Importação desativada'];
    }

    public static function buscarNfePorPedido(PedidoBlingStaging $staging): bool
    {
        return false;
    }

    public static function buscarDadosEnvio(PedidoBlingStaging $staging): bool
    {
        return false;
    }

    public static function buscarCustosProdutos(PedidoBlingStaging $staging): int
    {
        return 0;
    }

    public static function reprocessarImpostos(string $blingAccount, int $mes, int $ano): array
    {
        return ['atualizados' => 0, 'erro' => 'Importação desativada'];
    }
}

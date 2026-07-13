<?php

namespace Tests\Unit;

use App\Models\LoteRecebimento;
use Tests\TestCase;

class LoteRecebimentoTest extends TestCase
{
    public function test_gera_descricao_do_lote_a_partir_do_banco_e_data(): void
    {
        $descricao = LoteRecebimento::gerarDescricao('Banco do Brasil', '2026-07-13');

        $this->assertSame('Banco do Brasil - 13/07/2026', $descricao);
    }

    public function test_usa_identificador_manual_quando_fornecido(): void
    {
        $descricao = LoteRecebimento::gerarDescricao('Banco do Brasil', '2026-07-13', 'Repasse Shopee');

        $this->assertSame('Repasse Shopee', $descricao);
    }
}

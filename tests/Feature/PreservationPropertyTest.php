<?php

namespace Tests\Feature;

use App\Filament\Pages\DashboardVendas;
use App\Models\CanalVenda;
use App\Models\Cnpj;
use App\Models\ContaReceber;
use App\Models\User;
use App\Models\Venda;
use App\Services\ContaReceberService;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase as BaseTestCase;

/**
 * Preservation Property Tests
 *
 * These tests capture the EXISTING baseline behavior that must be preserved
 * after the bugfix is applied. They MUST PASS on the current unfixed code.
 *
 * DO NOT test for the 'repasse' key or CNPJ filtering — those are the bugs being fixed.
 *
 * **Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5, 3.6**
 */
class PreservationPropertyTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }

        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }

        if (!Schema::hasTable('model_has_roles')) {
            Schema::create('model_has_roles', function (Blueprint $table) {
                $table->unsignedBigInteger('role_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->primary(['role_id', 'model_id', 'model_type']);
            });
        }

        if (!Schema::hasTable('model_has_permissions')) {
            Schema::create('model_has_permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('permission_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->primary(['permission_id', 'model_id', 'model_type']);
            });
        }

        if (!Schema::hasTable('role_has_permissions')) {
            Schema::create('role_has_permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('permission_id');
                $table->unsignedBigInteger('role_id');
                $table->primary(['permission_id', 'role_id']);
            });
        }

        if (!Schema::hasTable('cnpjs')) {
            Schema::create('cnpjs', function (Blueprint $table) {
                $table->id('id_cnpj');
                $table->string('numero_cnpj');
                $table->string('razao_social');
                $table->string('regime_tributario')->nullable();
                $table->boolean('ativo')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('canais_venda')) {
            Schema::create('canais_venda', function (Blueprint $table) {
                $table->id('id_canal');
                $table->string('nome_canal');
                $table->string('tipo_nota')->nullable();
                $table->boolean('comissao_sobre_frete')->default(false);
                $table->boolean('imposto_sobre_frete')->default(false);
                $table->decimal('percentual_antecipacao', 5, 2)->default(0);
                $table->boolean('reembolso_valor_total')->default(false);
                $table->boolean('ativo')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('vendas')) {
            Schema::create('vendas', function (Blueprint $table) {
                $table->id('id_venda');
                $table->string('bling_id')->nullable();
                $table->string('bling_account')->nullable();
                $table->string('numero_pedido_canal')->nullable();
                $table->string('numero_nota_fiscal')->nullable();
                $table->string('nfe_chave_acesso')->nullable();
                $table->decimal('nfe_valor', 12, 2)->nullable();
                $table->decimal('valor_total_venda', 12, 2)->default(0);
                $table->decimal('total_produtos', 12, 2)->default(0);
                $table->decimal('custo_produtos', 12, 2)->default(0);
                $table->decimal('valor_frete_cliente', 12, 2)->default(0);
                $table->decimal('valor_frete_transportadora', 12, 2)->default(0);
                $table->decimal('frete_cotado', 12, 2)->nullable();
                $table->decimal('comissao', 12, 2)->default(0);
                $table->decimal('comissao_afiliado', 12, 2)->default(0);
                $table->decimal('subsidio_pix', 12, 2)->nullable();
                $table->decimal('subsidio_magalu', 12, 2)->nullable();
                $table->decimal('base_imposto', 12, 2)->nullable();
                $table->decimal('percentual_imposto', 5, 2)->nullable();
                $table->decimal('valor_imposto', 12, 2)->nullable();
                $table->unsignedBigInteger('id_canal')->nullable();
                $table->string('canal_nome')->nullable();
                $table->unsignedBigInteger('id_cnpj')->nullable();
                $table->date('data_venda')->nullable();
                $table->string('cliente_nome')->nullable();
                $table->string('cliente_documento')->nullable();
                $table->boolean('frete_pago')->default(false);
                $table->string('transportadora_manual')->nullable();
                $table->boolean('repasse_recebido')->default(false);
                $table->date('data_recebimento')->nullable();
                $table->date('data_prevista_envio')->nullable();
                $table->text('observacoes')->nullable();
                $table->string('bling_situacao_id')->nullable();
                $table->string('bling_situacao_nome')->nullable();
                $table->decimal('margem_frete', 12, 2)->default(0);
                $table->decimal('margem_produto', 12, 2)->default(0);
                $table->decimal('margem_venda_total', 12, 2)->default(0);
                $table->decimal('margem_contribuicao', 5, 2)->default(0);
                $table->string('ml_tipo_anuncio')->nullable();
                $table->string('ml_tipo_frete')->nullable();
                $table->boolean('ml_tem_rebate')->default(false);
                $table->decimal('ml_valor_rebate', 12, 2)->nullable();
                $table->decimal('ml_sale_fee', 12, 2)->nullable();
                $table->decimal('ml_frete_custo', 12, 2)->nullable();
                $table->decimal('ml_frete_receita', 12, 2)->nullable();
                $table->string('ml_order_id')->nullable();
                $table->string('ml_shipping_id')->nullable();
                $table->boolean('planilha_processada')->default(false);
                $table->boolean('planilha_afiliado_processada')->default(false);
                $table->boolean('cancelada')->default(false);
                $table->boolean('dre_lancado')->default(false);
                $table->timestamp('dre_lancado_em')->nullable();
                $table->decimal('cupom_shopee', 12, 2)->nullable();
                $table->string('cupom_shopee_descricao')->nullable();
                $table->decimal('cupom_plataforma', 12, 2)->nullable();
                $table->decimal('cupom_vendedor', 12, 2)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('contas_receber')) {
            Schema::create('contas_receber', function (Blueprint $table) {
                $table->id('id_conta_receber');
                $table->unsignedBigInteger('id_venda')->nullable();
                $table->decimal('valor_parcela', 12, 2)->default(0);
                $table->date('data_vencimento')->nullable();
                $table->date('data_recebimento')->nullable();
                $table->string('status')->default('pendente');
                $table->integer('numero_parcela')->default(1);
                $table->integer('total_parcelas')->default(1);
                $table->string('forma_pagamento')->nullable();
                $table->text('observacoes')->nullable();
                $table->boolean('lancamento_manual')->default(false);
                $table->boolean('estorno_pendente')->default(false);
                $table->unsignedBigInteger('conta_bancaria_id')->nullable();
                $table->unsignedBigInteger('categoria_id')->nullable();
                $table->unsignedBigInteger('lote_recebimento_id')->nullable();
                $table->unsignedBigInteger('transferencia_id')->nullable();
                $table->unsignedBigInteger('fatura_recebimento_id')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('lotes_recebimento')) {
            Schema::create('lotes_recebimento', function (Blueprint $table) {
                $table->id();
                $table->date('data_recebimento');
                $table->string('descricao')->nullable();
                $table->decimal('valor_total', 12, 2)->default(0);
                $table->integer('quantidade_contas')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('faturas_recebimento')) {
            Schema::create('faturas_recebimento', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('canal_id')->nullable();
                $table->string('descricao')->nullable();
                $table->date('data_prevista')->nullable();
                $table->string('status')->default('aberta');
                $table->decimal('valor_total', 12, 2)->default(0);
                $table->unsignedBigInteger('conta_bancaria_id')->nullable();
                $table->unsignedBigInteger('lote_recebimento_id')->nullable();
                $table->json('descontos')->nullable();
                $table->json('entradas_avulsas')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('canal_cnpj')) {
            Schema::create('canal_cnpj', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_canal');
                $table->unsignedBigInteger('cnpj_id');
                $table->boolean('ativo')->default(true);
                $table->timestamps();
                $table->unique(['id_canal', 'cnpj_id']);
            });
        }

        // Reset Spatie permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Grant all permissions via Gate::before
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            return true;
        });
    }

    /**
     * Helper: Create an admin user for dashboard access.
     */
    private function createAdminUser(): User
    {
        \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');
        return $user;
    }

    /**
     * Property: For all vendas with any filter combination,
     * totais['total'] == SUM(vendas.valor_total_venda)
     *
     * This verifies that the Dashboard's total KPI is calculated as a direct sum
     * of valor_total_venda from the vendas table (bruto value).
     *
     * **Validates: Requirements 3.5**
     */
    public function test_property_totais_total_equals_sum_valor_total_venda(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $faker = \Faker\Factory::create();

        $canal = CanalVenda::create([
            'nome_canal' => 'Canal Test Total',
            'ativo' => true,
        ]);

        // Generate random vendas with varied values
        $expectedTotal = 0.0;
        $numVendas = $faker->numberBetween(3, 15);

        for ($i = 0; $i < $numVendas; $i++) {
            $valorTotal = $faker->randomFloat(2, 50, 10000);
            $expectedTotal += $valorTotal;

            Venda::create([
                'bling_account' => $faker->randomElement(['primary', 'secondary']),
                'numero_pedido_canal' => 'PRES-T-' . $faker->unique()->randomNumber(6),
                'valor_total_venda' => $valorTotal,
                'total_produtos' => $faker->randomFloat(2, 50, $valorTotal),
                'margem_venda_total' => $faker->randomFloat(2, -500, 2000),
                'id_canal' => $canal->id_canal,
                'canal_nome' => 'Canal Test Total',
                'data_venda' => now(),
                'cancelada' => false,
            ]);
        }

        $dashboard = new DashboardVendas();
        $dashboard->periodo = 'este_mes';
        $dashboard->mes_selecionado = now()->format('Y-m');

        $totais = $dashboard->getTotaisProperty();

        $this->assertArrayHasKey('total', $totais);
        $this->assertEqualsWithDelta(
            round($expectedTotal, 2),
            round($totais['total'], 2),
            0.01,
            "totais['total'] must equal SUM(vendas.valor_total_venda). " .
            "Expected: {$expectedTotal}, Got: {$totais['total']}"
        );
    }

    /**
     * Property: For all vendas with any filter combination,
     * totais['lucro'] == SUM(vendas.margem_venda_total)
     *
     * This verifies that the Dashboard's lucro KPI is calculated as a direct sum
     * of margem_venda_total from the vendas table.
     *
     * **Validates: Requirements 3.5**
     */
    public function test_property_totais_lucro_equals_sum_margem_venda_total(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $faker = \Faker\Factory::create();

        $canal = CanalVenda::create([
            'nome_canal' => 'Canal Test Lucro',
            'ativo' => true,
        ]);

        // Generate random vendas with varied margem values (can be negative)
        $expectedLucro = 0.0;
        $numVendas = $faker->numberBetween(3, 15);

        for ($i = 0; $i < $numVendas; $i++) {
            $valorTotal = $faker->randomFloat(2, 100, 8000);
            $margemTotal = $faker->randomFloat(2, -1000, 3000);
            $expectedLucro += $margemTotal;

            Venda::create([
                'bling_account' => $faker->randomElement(['primary', 'secondary']),
                'numero_pedido_canal' => 'PRES-L-' . $faker->unique()->randomNumber(6),
                'valor_total_venda' => $valorTotal,
                'total_produtos' => $valorTotal,
                'margem_venda_total' => $margemTotal,
                'id_canal' => $canal->id_canal,
                'canal_nome' => 'Canal Test Lucro',
                'data_venda' => now(),
                'cancelada' => false,
            ]);
        }

        $dashboard = new DashboardVendas();
        $dashboard->periodo = 'este_mes';
        $dashboard->mes_selecionado = now()->format('Y-m');

        $totais = $dashboard->getTotaisProperty();

        $this->assertArrayHasKey('lucro', $totais);
        $this->assertEqualsWithDelta(
            round($expectedLucro, 2),
            round($totais['lucro'], 2),
            0.01,
            "totais['lucro'] must equal SUM(vendas.margem_venda_total). " .
            "Expected: {$expectedLucro}, Got: {$totais['lucro']}"
        );
    }

    /**
     * Property: For all ContaReceber with status == 'recebido' OR lote_recebimento_id IS NOT NULL,
     * calling regenerar() does NOT change valor_parcela.
     *
     * This confirms that locked (travadas) ContasReceber are never modified by
     * the regeneration process.
     *
     * **Validates: Requirements 3.2**
     */
    public function test_property_regenerar_does_not_modify_locked_contas_receber(): void
    {
        $faker = \Faker\Factory::create();

        $canal = CanalVenda::create([
            'nome_canal' => 'Mercado Livre',
            'ativo' => true,
        ]);

        // Generate multiple locked ContasReceber with varied scenarios
        $iterations = $faker->numberBetween(5, 20);

        for ($i = 0; $i < $iterations; $i++) {
            $valorTotal = $faker->randomFloat(2, 200, 5000);
            $valorParcela = $faker->randomFloat(2, 100, $valorTotal);
            $comissao = $faker->randomFloat(2, 10, 200);
            $afiliado = $faker->randomFloat(2, 0, 50);

            $venda = Venda::create([
                'bling_account' => $faker->randomElement(['primary', 'secondary']),
                'numero_pedido_canal' => 'PRES-R-' . $faker->unique()->randomNumber(6),
                'valor_total_venda' => $valorTotal,
                'total_produtos' => $valorTotal - $faker->randomFloat(2, 0, 50),
                'valor_frete_cliente' => $faker->randomFloat(2, 0, 100),
                'comissao' => $comissao,
                'comissao_afiliado' => $afiliado,
                'margem_venda_total' => $valorTotal - $comissao - $afiliado,
                'id_canal' => $canal->id_canal,
                'canal_nome' => 'Mercado Livre',
                'data_venda' => now(),
                'nfe_chave_acesso' => 'NFE' . $faker->randomNumber(8),
                'frete_pago' => true,
                'planilha_processada' => true,
                'cancelada' => false,
            ]);

            // Randomly lock via status='recebido' or lote_recebimento_id
            $lockType = $faker->randomElement(['recebido', 'lote', 'both']);

            $contaData = [
                'id_venda' => $venda->id_venda,
                'valor_parcela' => $valorParcela,
                'data_vencimento' => now()->addDays(30),
                'numero_parcela' => 1,
                'total_parcelas' => 1,
                'forma_pagamento' => 'Mercado Livre',
                'lancamento_manual' => false,
            ];

            if ($lockType === 'recebido') {
                $contaData['status'] = 'recebido';
                $contaData['lote_recebimento_id'] = null;
            } elseif ($lockType === 'lote') {
                $contaData['status'] = 'pendente';
                $contaData['lote_recebimento_id'] = $faker->numberBetween(1, 100);
            } else {
                $contaData['status'] = 'recebido';
                $contaData['lote_recebimento_id'] = $faker->numberBetween(1, 100);
            }

            $conta = ContaReceber::create($contaData);
            $originalValor = $conta->valor_parcela;

            // Now change the venda's comissao_afiliado (trigger for regenerar)
            $venda->update(['comissao_afiliado' => $faker->randomFloat(2, 5, 100)]);

            // Call regenerar
            ContaReceberService::regenerar($venda->fresh());

            // Refresh the conta and verify valor_parcela is UNCHANGED
            $conta->refresh();

            $this->assertEquals(
                $originalValor,
                $conta->valor_parcela,
                "ContaReceber #{$conta->id_conta_receber} with lock type '{$lockType}' " .
                "should NOT have valor_parcela modified by regenerar(). " .
                "Original: {$originalValor}, After: {$conta->valor_parcela}"
            );
        }
    }

    /**
     * Property: When conta IS NULL, getCanaisOptions(null) returns all active canais.
     *
     * This verifies that when no account is selected ("Todas"), the dropdown
     * shows ALL active canais from the database.
     *
     * **Validates: Requirements 3.1**
     */
    public function test_property_no_conta_selected_returns_all_active_canais(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $faker = \Faker\Factory::create();

        // Create random number of canais, some active some inactive
        $numCanais = $faker->numberBetween(3, 10);
        $activeCanais = [];

        for ($i = 0; $i < $numCanais; $i++) {
            $isActive = $faker->boolean(70); // 70% active
            $nome = 'Canal-' . $faker->unique()->word() . '-' . $i;

            CanalVenda::create([
                'nome_canal' => $nome,
                'ativo' => $isActive,
            ]);

            if ($isActive) {
                $activeCanais[] = $nome;
            }
        }

        // Dashboard with NO conta selected
        $dashboard = new DashboardVendas();
        $dashboard->conta = null;

        $form = $dashboard->form(\Filament\Forms\Form::make($dashboard));
        $canalField = null;

        foreach ($form->getComponents() as $component) {
            if (method_exists($component, 'getChildComponents')) {
                foreach ($component->getChildComponents() as $child) {
                    if ($child instanceof \Filament\Forms\Components\Select && $child->getName() === 'canal') {
                        $canalField = $child;
                        break 2;
                    }
                }
            }
        }

        $this->assertNotNull($canalField, 'Canal Select field should exist in the form');
        $options = $canalField->getOptions();

        // ALL active canais must be present in the dropdown
        foreach ($activeCanais as $activeCanal) {
            $this->assertArrayHasKey(
                $activeCanal,
                $options,
                "Active canal '{$activeCanal}' must appear in dropdown when no conta is selected"
            );
        }

        // Verify that the count of options equals the total number of active canais in the DB
        $totalActiveInDb = CanalVenda::where('ativo', true)->count();
        $this->assertCount(
            $totalActiveInDb,
            $options,
            "Dropdown should contain exactly {$totalActiveInDb} active canais when no conta is selected, got " . count($options)
        );
    }

    /**
     * Property: Canal linked to both CNPJs appears in dropdown regardless of conta.
     *
     * On the current UNFIXED code, ALL active canais appear regardless of conta
     * (because there is no CNPJ filtering). This test verifies that a canal
     * linked to both CNPJs always appears — which is the current behavior and
     * must continue to be true after the fix.
     *
     * **Validates: Requirements 3.4**
     */
    public function test_property_shared_canal_appears_regardless_of_conta(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        // Create a shared canal (linked to both CNPJs)
        $canalShared = CanalVenda::create([
            'nome_canal' => 'Mercado Livre Shared',
            'ativo' => true,
        ]);

        // Create CNPJs and link the shared canal to both
        $cnpjPrimary = Cnpj::create([
            'numero_cnpj' => '33.333.333/0001-33',
            'razao_social' => 'Mobilia Test',
            'ativo' => true,
        ]);

        $cnpjSecondary = Cnpj::create([
            'numero_cnpj' => '44.444.444/0001-44',
            'razao_social' => 'HES Test',
            'ativo' => true,
        ]);

        DB::table('canal_cnpj')->insert([
            ['id_canal' => $canalShared->id_canal, 'cnpj_id' => $cnpjPrimary->id_cnpj, 'ativo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id_canal' => $canalShared->id_canal, 'cnpj_id' => $cnpjSecondary->id_cnpj, 'ativo' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Test with each possible conta value and null
        foreach ([null, 'primary', 'secondary'] as $conta) {
            $dashboard = new DashboardVendas();
            $dashboard->conta = $conta;

            $form = $dashboard->form(\Filament\Forms\Form::make($dashboard));
            $canalField = null;

            foreach ($form->getComponents() as $component) {
                if (method_exists($component, 'getChildComponents')) {
                    foreach ($component->getChildComponents() as $child) {
                        if ($child instanceof \Filament\Forms\Components\Select && $child->getName() === 'canal') {
                            $canalField = $child;
                            break 2;
                        }
                    }
                }
            }

            $this->assertNotNull($canalField);
            $options = $canalField->getOptions();

            $this->assertArrayHasKey(
                'Mercado Livre Shared',
                $options,
                "Shared canal 'Mercado Livre Shared' must appear when conta='{$conta}'. " .
                "Available options: [" . implode(', ', array_keys($options)) . "]"
            );
        }
    }

    /**
     * Property: When canal_cnpj table is empty (no configuration),
     * all active canais appear as fallback.
     *
     * On the current UNFIXED code, the canal_cnpj table doesn't even exist in production,
     * so this test confirms the fallback behavior: all active canais always appear.
     *
     * **Validates: Requirements 3.6**
     */
    public function test_property_empty_canal_cnpj_returns_all_active_canais_as_fallback(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $faker = \Faker\Factory::create();

        // Ensure canal_cnpj table is EMPTY
        DB::table('canal_cnpj')->truncate();

        // Create active canais
        $activeNames = [];
        $numCanais = $faker->numberBetween(3, 8);

        for ($i = 0; $i < $numCanais; $i++) {
            $nome = 'Fallback-Canal-' . $faker->unique()->word() . '-' . $i;
            CanalVenda::create([
                'nome_canal' => $nome,
                'ativo' => true,
            ]);
            $activeNames[] = $nome;
        }

        // Test with conta selected — even with no canal_cnpj config,
        // the current code returns ALL canais (fallback behavior)
        foreach (['primary', 'secondary'] as $conta) {
            $dashboard = new DashboardVendas();
            $dashboard->conta = $conta;

            $form = $dashboard->form(\Filament\Forms\Form::make($dashboard));
            $canalField = null;

            foreach ($form->getComponents() as $component) {
                if (method_exists($component, 'getChildComponents')) {
                    foreach ($component->getChildComponents() as $child) {
                        if ($child instanceof \Filament\Forms\Components\Select && $child->getName() === 'canal') {
                            $canalField = $child;
                            break 2;
                        }
                    }
                }
            }

            $this->assertNotNull($canalField);
            $options = $canalField->getOptions();

            // With empty canal_cnpj, all active canais must appear (fallback)
            foreach ($activeNames as $name) {
                $this->assertArrayHasKey(
                    $name,
                    $options,
                    "With empty canal_cnpj table and conta='{$conta}', canal '{$name}' " .
                    "must appear as fallback. Options: [" . implode(', ', array_keys($options)) . "]"
                );
            }

            $totalActiveInDb = CanalVenda::where('ativo', true)->count();
            $this->assertCount(
                $totalActiveInDb,
                $options,
                "With empty canal_cnpj and conta='{$conta}', dropdown should contain all {$totalActiveInDb} active canais"
            );
        }
    }
}

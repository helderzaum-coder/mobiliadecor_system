<?php

namespace Tests\Feature;

use App\Filament\Pages\DashboardVendas;
use App\Models\CanalVenda;
use App\Models\Cnpj;
use App\Models\ContaReceber;
use App\Models\User;
use App\Models\Venda;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase as BaseTestCase;

/**
 * Bug Condition Exploration Test
 *
 * This test encodes the EXPECTED behavior after the fix is implemented.
 * It MUST FAIL on unfixed code — failure confirms the bugs exist.
 *
 * Bug 1: getTotaisProperty() does not return 'repasse' key (sum of contas_receber.valor_parcela)
 * Bug 2: Canal dropdown returns ALL canais regardless of selected conta (no CNPJ filtering)
 *
 * **Validates: Requirements 1.1, 1.3, 1.4, 2.1, 2.3**
 */
class BugConditionExplorationTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create required tables for testing in SQLite
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

        // Create canal_cnpj pivot table (needed for Bug 2 expected behavior)
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
     * Bug 1 Exploration: getTotaisProperty() should return 'repasse' key
     *
     * This test creates vendas with associated ContasReceber and asserts that
     * getTotaisProperty() returns a 'repasse' key with the correct sum of valor_parcela.
     *
     * ON UNFIXED CODE: This test MUST FAIL because getTotaisProperty() does NOT
     * return a 'repasse' key — it only returns total, lucro, margem, etc.
     *
     * **Validates: Requirements 1.1, 2.1**
     */
    public function test_bug1_dashboard_totais_should_contain_repasse_key(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        // Create a canal
        $canal = CanalVenda::create([
            'nome_canal' => 'Mercado Livre',
            'ativo' => true,
        ]);

        // Create vendas with ContasReceber using varied values
        $faker = \Faker\Factory::create();
        $expectedRepasse = 0;

        for ($i = 0; $i < 5; $i++) {
            $valorTotal = $faker->randomFloat(2, 100, 5000);
            $valorParcela = $faker->randomFloat(2, 50, $valorTotal);
            $expectedRepasse += $valorParcela;

            $venda = Venda::create([
                'bling_account' => 'primary',
                'numero_pedido_canal' => 'TEST-' . $faker->unique()->randomNumber(6),
                'valor_total_venda' => $valorTotal,
                'total_produtos' => $valorTotal,
                'margem_venda_total' => $valorTotal * 0.2,
                'id_canal' => $canal->id_canal,
                'canal_nome' => 'Mercado Livre',
                'data_venda' => now(),
                'cancelada' => false,
            ]);

            ContaReceber::create([
                'id_venda' => $venda->id_venda,
                'valor_parcela' => $valorParcela,
                'data_vencimento' => now()->addDays(30),
                'status' => 'pendente',
                'numero_parcela' => 1,
                'total_parcelas' => 1,
                'forma_pagamento' => 'Mercado Livre',
            ]);
        }

        // Instantiate the DashboardVendas page and get totais
        $this->actingAs($user);
        $dashboard = new DashboardVendas();
        $dashboard->periodo = 'este_mes';
        $dashboard->mes_selecionado = now()->format('Y-m');

        $totais = $dashboard->getTotaisProperty();

        // BUG CONDITION: On unfixed code, 'repasse' key does NOT exist
        // This assertion encodes the EXPECTED behavior after fix
        $this->assertArrayHasKey(
            'repasse',
            $totais,
            "Bug 1 confirmed: getTotaisProperty() does NOT return 'repasse' key. " .
            "Counterexample: totais keys are [" . implode(', ', array_keys($totais)) . "] — 'repasse' is missing."
        );

        // If we reach here (after fix), verify the value is correct
        $this->assertEqualsWithDelta(
            round($expectedRepasse, 2),
            round($totais['repasse'], 2),
            0.01,
            "Repasse value should equal SUM(contas_receber.valor_parcela) for filtered vendas"
        );
    }

    /**
     * Bug 2 Exploration: Canal dropdown should filter by CNPJ when conta is selected
     *
     * This test creates canais linked to specific CNPJs via canal_cnpj pivot table,
     * then asserts that when a conta is selected, only canais belonging to
     * that conta's CNPJ are returned in the dropdown options.
     *
     * ON UNFIXED CODE: This test MUST FAIL because the dropdown uses
     * CanalVenda::orderBy('nome_canal')->pluck(...) without any CNPJ filtering.
     *
     * **Validates: Requirements 1.3, 1.4, 2.3**
     */
    public function test_bug2_canal_dropdown_should_filter_by_cnpj_when_conta_selected(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        // Create CNPJs
        $cnpjPrimary = Cnpj::create([
            'numero_cnpj' => '11.111.111/0001-11',
            'razao_social' => 'Mobilia Decor LTDA',
            'ativo' => true,
        ]);

        $cnpjSecondary = Cnpj::create([
            'numero_cnpj' => '22.222.222/0001-22',
            'razao_social' => 'HES Moveis LTDA',
            'ativo' => true,
        ]);

        // Create canais: one exclusive to primary, one exclusive to secondary, one shared
        $canalPrimary = CanalVenda::create([
            'nome_canal' => 'Site Mobilia',
            'ativo' => true,
        ]);

        $canalSecondary = CanalVenda::create([
            'nome_canal' => 'Site HES',
            'ativo' => true,
        ]);

        $canalShared = CanalVenda::create([
            'nome_canal' => 'Mercado Livre',
            'ativo' => true,
        ]);

        // Link canais to CNPJs via pivot table
        \Illuminate\Support\Facades\DB::table('canal_cnpj')->insert([
            ['id_canal' => $canalPrimary->id_canal, 'cnpj_id' => $cnpjPrimary->id_cnpj, 'ativo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id_canal' => $canalShared->id_canal, 'cnpj_id' => $cnpjPrimary->id_cnpj, 'ativo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id_canal' => $canalSecondary->id_canal, 'cnpj_id' => $cnpjSecondary->id_cnpj, 'ativo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id_canal' => $canalShared->id_canal, 'cnpj_id' => $cnpjSecondary->id_cnpj, 'ativo' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($user);

        // Simulate DashboardVendas with conta = 'primary'
        $dashboard = new DashboardVendas();
        $dashboard->conta = 'primary';

        // Get the canal dropdown options - on unfixed code this uses
        // CanalVenda::orderBy('nome_canal')->pluck('nome_canal', 'nome_canal')->toArray()
        // which returns ALL active canais without filtering
        $form = $dashboard->form(\Filament\Forms\Form::make($dashboard));
        $canalField = null;

        // Extract the canal Select field from the form schema
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

        // Get the options from the canal dropdown
        $options = $canalField->getOptions();

        // BUG CONDITION: On unfixed code, ALL canais appear regardless of conta
        // Expected behavior: only canais linked to primary CNPJ should appear
        // ('Site Mobilia' and 'Mercado Livre' — but NOT 'Site HES')
        $this->assertArrayNotHasKey(
            'Site HES',
            $options,
            "Bug 2 confirmed: Canal 'Site HES' (exclusive to secondary/HES) appears in dropdown " .
            "when conta='primary' is selected. Counterexample: dropdown returns ALL canais [" .
            implode(', ', array_keys($options)) . "] without filtering by CNPJ."
        );

        // Verify expected canais ARE present
        $this->assertArrayHasKey(
            'Site Mobilia',
            $options,
            "Canal 'Site Mobilia' (linked to primary) should appear when conta='primary'"
        );

        $this->assertArrayHasKey(
            'Mercado Livre',
            $options,
            "Canal 'Mercado Livre' (shared/linked to both) should appear when conta='primary'"
        );
    }
}

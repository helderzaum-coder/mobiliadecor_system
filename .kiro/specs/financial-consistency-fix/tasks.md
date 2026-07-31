# Implementation Plan

## Overview

This plan fixes two inter-related bugs in the financial system: (1) Dashboard de Vendas missing a repasse (net payout) totalizer in KPIs, showing only bruto values; (2) Canal dropdown not filtering by CNPJ/account. The fix follows the bug condition methodology: exploration tests first (confirm bugs exist), preservation tests (capture baseline), implementation, then validation.

## Tasks

- [x] 1. Write bug condition exploration test
  - **Property 1: Bug Condition** - Dashboard Missing Repasse Totalizer & Canal Without CNPJ Filter
  - **IMPORTANT**: Write this property-based test BEFORE implementing the fix
  - **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bug exists
  - **DO NOT attempt to fix the test or the code when it fails**
  - **NOTE**: This test encodes the expected behavior - it will validate the fix when it passes after implementation
  - **GOAL**: Surface counterexamples that demonstrate the bug exists
  - **Scoped PBT Approach**: Scope properties to concrete failing cases:
    - Bug 1: Create vendas with ContasReceber, assert `getTotaisProperty()` returns key 'repasse' with correct sum of `valor_parcela`
    - Bug 2: Create canais vinculados a CNPJ específico via `canal_cnpj`, set `$this->conta = 'primary'`, assert dropdown only returns canais do CNPJ correspondente
  - Test that `getTotaisProperty()` does NOT return 'repasse' key (confirms bug 1 exists on unfixed code)
  - Test that dropdown returns ALL canais regardless of conta selected (confirms bug 2 exists on unfixed code)
  - isBugCondition: `(page == 'DashboardVendas' AND action == 'viewTotals' AND repasseTotalNotDisplayed) OR (filter_conta IS NOT NULL AND dropdown == 'canal' AND canal NOT belongsTo(filter_conta.cnpj) AND canal IS displayed)`
  - expectedBehavior: `'repasse' IN getTotaisProperty().keys AND repasse == SUM(contas_receber.valor_parcela)` AND `getCanaisOptions(conta) only contains canais WITH relationship to corresponding cnpj_id`
  - Run test on UNFIXED code
  - **EXPECTED OUTCOME**: Test FAILS (this is correct - it proves the bug exists)
  - Document counterexamples found:
    - `getTotaisProperty()` returns array without 'repasse' key
    - Dropdown returns canais from all CNPJs regardless of selected conta
  - Mark task complete when test is written, run, and failure is documented
  - _Requirements: 1.1, 1.3, 1.4, 2.1, 2.3_

- [x] 2. Write preservation property tests (BEFORE implementing fix)
  - **Property 2: Preservation** - Valores Brutos, ContasReceber Travadas, e Dropdown Sem Filtro
  - **IMPORTANT**: Follow observation-first methodology
  - **Observation Phase** (run on UNFIXED code):
    - Observe: `getTotaisProperty()` returns `total` as sum of `valor_total_venda` from vendas table
    - Observe: `getTotaisProperty()` returns `lucro` as sum of `margem_venda_total` from vendas table
    - Observe: ContaReceber with status 'recebido' or `lote_recebimento_id` set — `regenerar()` does NOT modify `valor_parcela`
    - Observe: With no conta selected, dropdown returns ALL active canais
    - Observe: Canal linked to both CNPJs appears regardless of conta selected
  - **Property-Based Tests**:
    - Property: For all vendas with any filter combination, `totais['total']` == `SUM(vendas.valor_total_venda)` (from Preservation Requirement 3.5)
    - Property: For all vendas with any filter combination, `totais['lucro']` == `SUM(vendas.margem_venda_total)` (from Preservation Requirement 3.5)
    - Property: For all ContaReceber with status == 'recebido' OR lote_recebimento_id IS NOT NULL, calling `regenerar()` does NOT change `valor_parcela` (from Preservation Requirement 3.2)
    - Property: When conta IS NULL, `getCanaisOptions(null)` returns all active canais (from Preservation Requirement 3.1)
    - Property: Canal linked to both CNPJs appears in dropdown regardless of conta (from Preservation Requirement 3.4)
    - Property: When `canal_cnpj` table is empty (no configuration), all active canais appear as fallback (from Preservation Requirement 3.6)
  - Verify all tests PASS on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (this confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6_

- [x] 3. Fix for Financial Consistency (Dashboard Repasse + Canal CNPJ Filter)

  - [x] 3.1 Create migration for `canal_cnpj` pivot table
    - Create migration file `database/migrations/xxxx_create_canal_cnpj_table.php`
    - Table columns: `id` (auto-increment), `id_canal` (unsignedBigInteger, FK → canais_venda.id), `cnpj_id` (unsignedBigInteger, FK → cnpjs.id), `ativo` (boolean, default true), timestamps
    - Add unique constraint on (`id_canal`, `cnpj_id`) to prevent duplicates
    - A canal can belong to multiple CNPJs (many-to-many relationship)
    - _Bug_Condition: No relationship table exists between canais_venda and cnpjs_
    - _Expected_Behavior: Tabela pivot permite vincular canais a CNPJs específicos_
    - _Preservation: When table is empty, system falls back to showing all active canais_
    - _Requirements: 2.3, 2.4, 3.6_

  - [x] 3.2 Add `cnpjs()` relationship to CanalVenda model
    - File: `app/Models/CanalVenda.php`
    - Add `belongsToMany` relationship: `cnpjs()` pointing to `canal_cnpj` pivot table with keys `id_canal` and `cnpj_id`
    - This enables querying which CNPJs a canal belongs to, and vice-versa
    - _Bug_Condition: CanalVenda has no relationship to CNPJ/empresa_
    - _Expected_Behavior: Canal can be queried by CNPJ relationship_
    - _Preservation: Existing model behavior and other relationships unchanged_
    - _Requirements: 2.3, 2.4_

  - [x] 3.3 Filter canal dropdown by selected account in DashboardVendas
    - File: `app/Filament/Pages/DashboardVendas.php`, function `form()`
    - Change `Select::make('canal')` from static `->options(fn () => ...)` to reactive `->options(fn ($get) => ...)`
    - When `conta` is not selected (null/"Todas"): return all active canais (fallback, preserves current behavior)
    - When `conta` is selected: get `cnpj_id` from config `bling.accounts.{conta}.cnpj_id`, query canais that have relationship in `canal_cnpj` with that `cnpj_id`
    - If no canais are configured for that CNPJ in `canal_cnpj` (empty result): fallback to all active canais
    - Add `->reactive()` to the `conta` Select to trigger re-evaluation of canal options
    - _Bug_Condition: Dropdown uses CanalVenda::orderBy('nome_canal')->pluck(...) without filtering by CNPJ_
    - _Expected_Behavior: Dropdown shows only canais belonging to selected conta's CNPJ, with fallback to all when empty_
    - _Preservation: When no conta selected, all active canais still appear (Requirement 3.1); canais in both CNPJs appear regardless (Requirement 3.4)_
    - _Requirements: 2.3, 2.4, 3.1, 3.4, 3.6_

  - [x] 3.4 Add repasse totalizer to `getTotaisProperty()`
    - File: `app/Filament/Pages/DashboardVendas.php`, function `getTotaisProperty()`
    - After existing `$total` and `$lucro` calculations, add query:
      - Sum `valor_parcela` from `contas_receber` WHERE `id_venda` IN the filtered vendas set (same filters as existing query)
      - Store result as `$repasse`
    - Return `'repasse' => $repasse` in the totais array alongside existing `total`, `lucro`, `margem` keys
    - Ensure existing `total` and `lucro` calculations remain UNCHANGED (same sum from vendas table)
    - _Bug_Condition: getTotaisProperty() only returns valor_total_venda (bruto), no repasse key_
    - _Expected_Behavior: getTotaisProperty() returns 'repasse' key with SUM(contas_receber.valor_parcela) for filtered vendas_
    - _Preservation: totais['total'] and totais['lucro'] continue as sum from vendas table (Requirement 3.5)_
    - _Requirements: 2.1, 2.2, 3.5_

  - [x] 3.5 Display repasse KPI in blade template
    - File: `resources/views/filament/pages/dashboard-vendas.blade.php`
    - Add new KPI card displaying `$totais['repasse']` formatted as currency (R$)
    - Place alongside existing "Total" KPI card
    - Label: "Repasse Esperado" with differentiated icon/color to distinguish from bruto
    - Handle null/zero gracefully (display R$ 0,00 when no contas_receber exist)
    - _Bug_Condition: Dashboard only shows valor_total_venda in KPI without repasse visibility_
    - _Expected_Behavior: Dashboard shows both bruto and repasse values clearly distinguished_
    - _Preservation: Existing KPI cards for Total and Lucro remain unchanged_
    - _Requirements: 2.1_

  - [x] 3.6 Reset canal filter when account changes
    - File: `app/Filament/Pages/DashboardVendas.php`
    - Add or update `updatedConta()` method (Livewire lifecycle hook)
    - When conta changes: set `$this->canal = null` to clear the canal selection
    - This prevents a canal from the previous account remaining selected when it doesn't belong to the new account
    - _Bug_Condition: Changing conta does not reset canal, leaving invalid canal selected_
    - _Expected_Behavior: Canal resets to null when conta changes, forcing user to re-select_
    - _Preservation: Other filter resets and behaviors remain unchanged_
    - _Requirements: 2.3, 2.4_

  - [x] 3.7 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Dashboard Returns Repasse & Canal Filtered by CNPJ
    - **IMPORTANT**: Re-run the SAME test from task 1 - do NOT write a new test
    - The test from task 1 encodes the expected behavior
    - When this test passes, it confirms the expected behavior is satisfied:
      - `getTotaisProperty()` now returns 'repasse' key with correct sum
      - Dropdown now filters canais by CNPJ when conta is selected
    - Run bug condition exploration test from step 1
    - **EXPECTED OUTCOME**: Test PASSES (confirms bug is fixed)
    - _Requirements: 2.1, 2.2, 2.3, 2.4_

  - [x] 3.8 Verify preservation tests still pass
    - **Property 2: Preservation** - Valores Brutos, ContasReceber Travadas, e Dropdown Sem Filtro
    - **IMPORTANT**: Re-run the SAME tests from task 2 - do NOT write new tests
    - Run preservation property tests from step 2
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm all preservation properties hold:
      - `totais['total']` still equals SUM(vendas.valor_total_venda)
      - `totais['lucro']` still equals SUM(vendas.margem_venda_total)
      - ContasReceber travadas are not modified by regenerar()
      - All canais appear when no conta selected
      - Shared canais appear regardless of conta
      - Empty canal_cnpj falls back to all canais
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6_

- [x] 4. Checkpoint - Ensure all tests pass
  - Run full test suite: `php artisan test`
  - Ensure bug condition exploration test (task 1) PASSES after fix
  - Ensure preservation property tests (task 2) still PASS after fix
  - Ensure no regressions in existing test suite
  - Verify migration runs cleanly: `php artisan migrate`
  - Ask the user if questions arise or if manual verification is needed for UI elements (blade template KPI rendering)


## Task Dependency Graph

```json
{
  "waves": [
    {"tasks": ["1", "2"]},
    {"tasks": ["3.1", "3.4"]},
    {"tasks": ["3.2", "3.5"]},
    {"tasks": ["3.3", "3.6"]},
    {"tasks": ["3.7"]},
    {"tasks": ["3.8"]},
    {"tasks": ["4"]}
  ]
}
```

## Notes

- Tasks 1 and 2 MUST be completed BEFORE any implementation tasks (3.x) to establish baseline behavior
- Task 1 is expected to FAIL on unfixed code (this confirms the bug exists)
- Task 2 is expected to PASS on unfixed code (this captures behavior to preserve)
- After implementation (3.1-3.6), tasks 3.7 and 3.8 re-run the SAME tests without modification
- The `canal_cnpj` pivot table requires manual population after migration (seeder or admin UI)
- Blade template changes (3.5) may require manual visual verification

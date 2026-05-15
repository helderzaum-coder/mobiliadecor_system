<?php

namespace App\Filament\Resources\TrocaTampoConfigResource\Pages;

use App\Filament\Resources\TrocaTampoConfigResource;
use App\Jobs\VariacaoTamposJob;
use App\Models\TrocaTampoConfig;
use App\Services\Bling\BlingClient;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Log;

class ListTrocaTampoConfigs extends ListRecords
{
    protected static string $resource = TrocaTampoConfigResource::class;

    protected function getHeaderActions(): array
    {
        $grupoCorOptions = TrocaTampoConfig::query()
            ->select('grupo', 'cor')
            ->distinct()
            ->get()
            ->mapWithKeys(fn ($r) => ["{$r->grupo}|{$r->cor}" => "{$r->grupo} — {$r->cor}"])
            ->toArray();

        $corTampoOptions = TrocaTampoConfig::query()
            ->select('cor_tampo')
            ->distinct()
            ->pluck('cor_tampo', 'cor_tampo')
            ->toArray();

        return [
            Actions\CreateAction::make(),

            Actions\Action::make('lancar_estoque')
                ->label('Lançar Estoque por Grupo')
                ->icon('heroicon-o-pencil-square')
                ->color('success')
                ->form([
                    Forms\Components\Select::make('grupo_cor')
                        ->label('Grupo + Cor')
                        ->options($grupoCorOptions)
                        ->required()
                        ->searchable(),
                    Forms\Components\TextInput::make('novo_saldo')
                        ->label('Novo Saldo (todas variações)')
                        ->numeric()
                        ->required()
                        ->minValue(0),
                ])
                ->action(function (array $data) {
                    [$grupo, $cor] = explode('|', $data['grupo_cor']);
                    $saldo = (int) $data['novo_saldo'];

                    $configs = TrocaTampoConfig::where('grupo', $grupo)
                        ->where('cor', $cor)
                        ->where('equalizacao_ativa', true)
                        ->get();

                    if ($configs->isEmpty()) {
                        Notification::make()->title('Nenhum produto ativo encontrado neste grupo/cor.')->warning()->send();
                        return;
                    }

                    $client = new BlingClient('primary');
                    $depositoId = self::getDepositoGeral($client);

                    if (!$depositoId) {
                        Notification::make()->title('Depósito Geral não encontrado no Bling.')->danger()->send();
                        return;
                    }

                    $ok = 0;
                    $erros = 0;

                    foreach ($configs as $config) {
                        $produto = $client->getProductBySku($config->sku_produto);
                        if (!$produto) {
                            $erros++;
                            continue;
                        }

                        $res = $client->post('/estoques', [], [
                            'produto' => ['id' => (int) $produto['id']],
                            'deposito' => ['id' => $depositoId],
                            'operacao' => 'B',
                            'preco' => 0,
                            'custo' => 0,
                            'quantidade' => $saldo,
                            'observacoes' => "Lançamento manual: {$grupo}/{$cor} saldo={$saldo}",
                        ]);

                        $res['success'] ? $ok++ : $erros++;
                    }

                    // Atualizar estoque interno também
                    foreach ($configs as $config) {
                        // Garantir que o produto existe no estoque interno
                        \App\Models\ProdutoEstoque::firstOrCreate(
                            ['sku' => $config->sku_produto],
                            ['nome' => $config->nome_produto, 'formato' => 'S', 'saldo' => 0, 'saldo_fisico' => 0, 'saldo_virtual' => 0]
                        );

                        \App\Services\EstoqueService::balanco(
                            $config->sku_produto,
                            $saldo,
                            'manual',
                            "Lançamento tampo: {$grupo}/{$cor} saldo={$saldo}",
                            auth()->id(),
                            false // syncBling = false, já atualizou direto acima
                        );
                    }

                    Notification::make()
                        ->title("Estoque lançado: {$grupo} / {$cor}")
                        ->body("Saldo: {$saldo} | Atualizados: {$ok} | Erros: {$erros}")
                        ->color($erros ? 'warning' : 'success')
                        ->send();

                    Log::info("LancarEstoqueTampo: {$grupo}/{$cor} saldo={$saldo} ok={$ok} erros={$erros}");
                }),

            Actions\Action::make('equalizar_variacoes')
                ->label('Equalizar Variação de Tampos')
                ->icon('heroicon-o-squares-2x2')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Variação de Tampos')
                ->modalDescription('Equaliza o estoque de todas as variações de tampo pela média do grupo. Útil quando os saldos ficaram dessincronizados.')
                ->action(function () {
                    VariacaoTamposJob::dispatch('primary');
                    Notification::make()
                        ->title('Equalização enviada para processamento')
                        ->body('Você receberá uma notificação quando concluir.')
                        ->info()
                        ->send();
                }),

            Actions\Action::make('pausar_por_tampo')
                ->label('Pausar por Cor de Tampo')
                ->icon('heroicon-o-pause')
                ->color('warning')
                ->form([
                    Forms\Components\Select::make('cor_tampo')
                        ->label('Cor do Tampo')
                        ->options($corTampoOptions)
                        ->required()
                        ->searchable(),
                ])
                ->action(function (array $data) {
                    $affected = TrocaTampoConfig::where('cor_tampo', $data['cor_tampo'])
                        ->where('equalizacao_ativa', true)
                        ->update(['equalizacao_ativa' => false]);

                    Notification::make()
                        ->title("Tampo '{$data['cor_tampo']}' pausado")
                        ->body("{$affected} produto(s) com equalização desativada.")
                        ->warning()
                        ->send();
                }),
        ];
    }

    private static function getDepositoGeral(BlingClient $client): ?int
    {
        $res = $client->get('/depositos', ['limite' => 100]);
        if (!$res['success']) return null;

        foreach ($res['body']['data'] ?? [] as $d) {
            if (str_contains(strtolower(trim($d['descricao'] ?? '')), 'geral')) {
                return (int) $d['id'];
            }
        }

        return (int) (($res['body']['data'][0] ?? [])['id'] ?? 0) ?: null;
    }
}

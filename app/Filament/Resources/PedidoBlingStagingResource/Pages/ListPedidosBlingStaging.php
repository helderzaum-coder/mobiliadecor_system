<?php

namespace App\Filament\Resources\PedidoBlingStagingResource\Pages;

use App\Filament\Resources\PedidoBlingStagingResource;
use App\Services\Bling\BlingImportService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPedidosBlingStaging extends ListRecords
{
    protected static string $resource = PedidoBlingStagingResource::class;

    protected ?string $defaultTableSortColumn = 'data_pedido';
    protected ?string $defaultTableSortDirection = 'desc';

    public function getDefaultActiveTab(): ?string
    {
        return null;
    }

    /**
     * Redireciona para URL com filtros padrão se acessar sem filtros.
     * Evita carregar todos os 800+ pedidos e travar o sistema.
     */
    public function mount(): void
    {
        // Se não tem filtros na URL, redirecionar com filtros padrão
        if (empty(request()->query('tableFilters'))) {
            $url = static::getUrl() . '?' . http_build_query([
                'tableFilters' => [
                    'status' => ['value' => 'pendente'],
                    'periodo' => ['periodo_rapido' => 'este_mes'],
                ],
            ]);
            redirect($url);
            return;
        }

        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportar_csv')
                ->label('Exportar CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    $query = $this->getFilteredTableQuery();
                    $records = $query->get();

                    if ($records->isEmpty()) {
                        Notification::make()->title('Nenhum registro para exportar.')->warning()->send();
                        return;
                    }

                    $filename = 'revisao_pedidos_' . now()->format('Y-m-d_His') . '.csv';

                    return response()->streamDownload(function () use ($records) {
                        $handle = fopen('php://output', 'w');
                        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
                        fputcsv($handle, [
                            'Conta', 'Pedido Bling', 'Pedido Canal', 'Canal', 'Cliente',
                            'Data', 'Total Pedido', 'Frete Cliente', 'Custo Frete',
                            'Nota Fiscal', 'Comissão', 'Subsídio Pix', 'Imposto',
                            'Status', 'Cidade', 'UF', 'CEP',
                        ], ';');

                        foreach ($records as $r) {
                            fputcsv($handle, [
                                $r->bling_account === 'primary' ? 'Mobilia Decor' : 'HES Móveis',
                                $r->numero_pedido,
                                $r->numero_loja,
                                $r->canal,
                                $r->cliente_nome,
                                $r->data_pedido?->format('d/m/Y'),
                                number_format((float) $r->total_pedido, 2, ',', '.'),
                                number_format((float) $r->frete, 2, ',', '.'),
                                number_format((float) $r->custo_frete, 2, ',', '.'),
                                $r->nota_fiscal,
                                number_format((float) $r->comissao_calculada, 2, ',', '.'),
                                number_format((float) $r->subsidio_pix, 2, ',', '.'),
                                number_format((float) $r->valor_imposto, 2, ',', '.'),
                                $r->status,
                                $r->dest_cidade,
                                $r->dest_uf,
                                $r->dest_cep,
                            ], ';');
                        }

                        fclose($handle);
                    }, $filename);
                }),
            Actions\Action::make('reprocessar_impostos')
                ->label('Reprocessar Impostos')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->form([
                    Forms\Components\Select::make('bling_account')
                        ->label('Conta')
                        ->options([
                            'primary' => 'Mobilia Decor',
                            'secondary' => 'HES Móveis',
                        ])
                        ->required(),
                    Forms\Components\TextInput::make('mes')
                        ->label('Mês')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(12)
                        ->required(),
                    Forms\Components\TextInput::make('ano')
                        ->label('Ano')
                        ->numeric()
                        ->minValue(2020)
                        ->default(now()->year)
                        ->required(),
                ])
                ->modalHeading('Reprocessar Impostos do Mês')
                ->modalDescription('Recalcula o imposto de todos os pedidos do mês/conta (pendentes, aprovados e assistência) usando o percentual cadastrado em Impostos Mensais.')
                ->action(function (array $data) {
                    $result = BlingImportService::reprocessarImpostos(
                        $data['bling_account'],
                        (int) $data['mes'],
                        (int) $data['ano']
                    );

                    if (isset($result['erro'])) {
                        Notification::make()->title($result['erro'])->danger()->send();
                    } else {
                        Notification::make()
                            ->title("{$result['atualizados']} pedidos atualizados com {$result['percentual']}%")
                            ->success()
                            ->send();
                    }
                }),
        ];
    }
}

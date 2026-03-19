<?php

namespace App\Filament\Resources\TransportadoraResource\Pages;

use App\Filament\Resources\TransportadoraResource;
use App\Models\TransportadoraTabelaFrete;
use App\Models\TransportadoraTaxa;
use App\Models\TransportadoraUf;
use App\Services\TransportadoraExportService;
use App\Services\TransportadoraTaxaImportService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTransportadora extends EditRecord
{
    protected static string $resource = TransportadoraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('editar_faixa')
                ->label('Editar Faixa de Frete')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->form([
                    Forms\Components\TextInput::make('id_faixa')
                        ->label('ID da Faixa')
                        ->required()
                        ->numeric()
                        ->helperText('Veja o ID na tabela de faixas abaixo (coluna #)'),
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('uf')
                            ->label('UF')
                            ->maxLength(2)
                            ->extraInputAttributes(['style' => 'text-transform:uppercase']),
                        Forms\Components\TextInput::make('regiao')
                            ->label('Região'),
                        Forms\Components\TextInput::make('cep_inicio')
                            ->label('CEP Início')
                            ->maxLength(8),
                        Forms\Components\TextInput::make('cep_fim')
                            ->label('CEP Fim')
                            ->maxLength(8),
                        Forms\Components\TextInput::make('peso_min')
                            ->label('Peso Min (kg)')
                            ->numeric(),
                        Forms\Components\TextInput::make('peso_max')
                            ->label('Peso Max (kg)')
                            ->numeric(),
                        Forms\Components\TextInput::make('valor_fixo')
                            ->label('Valor Fixo')
                            ->numeric()
                            ->prefix('R$'),
                        Forms\Components\TextInput::make('valor_kg')
                            ->label('Valor/kg')
                            ->numeric()
                            ->prefix('R$'),
                        Forms\Components\TextInput::make('despacho')
                            ->label('Despacho')
                            ->numeric()
                            ->prefix('R$'),
                        Forms\Components\TextInput::make('adv_percentual')
                            ->label('ADV %')
                            ->numeric()
                            ->suffix('%'),
                        Forms\Components\TextInput::make('adv_minimo')
                            ->label('ADV Mínimo')
                            ->numeric()
                            ->prefix('R$'),
                        Forms\Components\TextInput::make('gris_percentual')
                            ->label('GRIS %')
                            ->numeric()
                            ->suffix('%'),
                        Forms\Components\TextInput::make('gris_minimo')
                            ->label('GRIS Mínimo')
                            ->numeric()
                            ->prefix('R$'),
                    ]),
                ])
                ->mountUsing(function (Forms\Form $form, array $arguments) {
                    // Pré-preencher se vier com id_faixa nos arguments
                })
                ->action(function (array $data) {
                    $faixa = TransportadoraTabelaFrete::find((int) $data['id_faixa']);
                    if (!$faixa || $faixa->id_transportadora !== $this->record->id_transportadora) {
                        Notification::make()->title('Faixa não encontrada ou não pertence a esta transportadora.')->danger()->send();
                        return;
                    }
                    $update = collect($data)->except('id_faixa')
                        ->filter(fn ($v) => $v !== null && $v !== '')
                        ->map(fn ($v) => is_string($v) ? (TransportadoraTaxaImportService::parseDecimal($v) ?? strtoupper(trim($v))) : $v)
                        ->toArray();
                    // UF e regiao são strings, não decimais
                    if (isset($data['uf'])) $update['uf'] = strtoupper(trim($data['uf']));
                    if (isset($data['regiao'])) $update['regiao'] = trim($data['regiao']);
                    $faixa->update($update);
                    Notification::make()->title('Faixa atualizada.')->success()->send();
                }),

            Actions\Action::make('excluir_faixa')
                ->label('Excluir Faixa')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->form([
                    Forms\Components\TextInput::make('id_faixa')
                        ->label('ID da Faixa')
                        ->required()
                        ->numeric()
                        ->helperText('Veja o ID na tabela de faixas abaixo (coluna #)'),
                ])
                ->action(function (array $data) {
                    $faixa = TransportadoraTabelaFrete::find((int) $data['id_faixa']);
                    if (!$faixa || $faixa->id_transportadora !== $this->record->id_transportadora) {
                        Notification::make()->title('Faixa não encontrada.')->danger()->send();
                        return;
                    }
                    $faixa->delete();
                    Notification::make()->title('Faixa excluída.')->success()->send();
                }),

            Actions\DeleteAction::make(),

            Actions\Action::make('exportar_frete')
                ->label('Baixar Tabela Frete')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->action(function () {
                    $csv = TransportadoraExportService::exportarTabelaFrete($this->record->id_transportadora);
                    $nome = 'frete_' . \Illuminate\Support\Str::slug($this->record->nome_transportadora) . '.csv';
                    return response()->streamDownload(function () use ($csv) {
                        echo "\xEF\xBB\xBF" . $csv;
                    }, $nome, ['Content-Type' => 'text/csv; charset=UTF-8']);
                })
                ->visible(fn () => $this->record->tabelaFrete()->count() > 0),

            Actions\Action::make('exportar_taxas')
                ->label('Baixar Taxas')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->action(function () {
                    $csv = TransportadoraExportService::exportarTaxas($this->record->id_transportadora);
                    $nome = 'taxas_' . \Illuminate\Support\Str::slug($this->record->nome_transportadora) . '.csv';
                    return response()->streamDownload(function () use ($csv) {
                        echo "\xEF\xBB\xBF" . $csv;
                    }, $nome, ['Content-Type' => 'text/csv; charset=UTF-8']);
                })
                ->visible(fn () => $this->record->taxas()->count() > 0),

            Actions\Action::make('importar_frete')
                ->label('Importar Tabela Frete')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->form([
                    Forms\Components\FileUpload::make('arquivo')
                        ->label('Planilha (CSV, XLSX ou ODS)')
                        ->acceptedFileTypes([
                            'text/csv',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.oasis.opendocument.spreadsheet',
                        ])
                        ->required()
                        ->disk('local')
                        ->directory('temp-imports')
                        ->helperText('Colunas: uf;cep_inicio;cep_fim;regiao;peso_min;peso_max;valor_kg;valor_fixo;frete_minimo;despacho;pedagio_valor;pedagio_fracao_kg;adv%;adv_min;gris%;gris_min'),
                    Forms\Components\Toggle::make('limpar_antes')
                        ->label('Limpar tabela existente antes de importar')
                        ->default(true)
                        ->helperText('Se ativado, remove todas as faixas atuais antes de importar'),
                ])
                ->action(function (array $data) {
                    $path = storage_path('app/private/' . $data['arquivo']);
                    if (!file_exists($path)) {
                        $path = storage_path('app/' . $data['arquivo']);
                    }
                    $resultado = TransportadoraTaxaImportService::importarTabelaFrete(
                        $path,
                        $this->record->id_transportadora,
                        $data['limpar_antes'] ?? false
                    );
                    @unlink($path);
                    $msg = "{$resultado['importados']} faixa(s) importada(s)";
                    if ($resultado['erros'] > 0) $msg .= ", {$resultado['erros']} erro(s)";
                    if (!empty($resultado['mensagens'])) $msg .= '. ' . implode('. ', $resultado['mensagens']);
                    Notification::make()->title($msg)
                        ->color($resultado['erros'] > 0 ? 'warning' : 'success')
                        ->send();
                }),

            Actions\Action::make('importar_taxas')
                ->label('Importar Taxas')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->form([
                    Forms\Components\FileUpload::make('arquivo')
                        ->label('Planilha (CSV, XLSX ou ODS)')
                        ->acceptedFileTypes([
                            'text/csv',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.oasis.opendocument.spreadsheet',
                        ])
                        ->required()
                        ->disk('local')
                        ->directory('temp-imports')
                        ->helperText('Colunas: tipo_taxa;uf;cidade;cep_inicio;cep_fim;valor_fixo;percentual;observacao'),
                    Forms\Components\Toggle::make('limpar_antes')
                        ->label('Limpar taxas existentes antes de importar')
                        ->default(true)
                        ->helperText('Se ativado, remove todas as taxas atuais antes de importar'),
                ])
                ->action(function (array $data) {
                    $path = storage_path('app/private/' . $data['arquivo']);
                    if (!file_exists($path)) {
                        $path = storage_path('app/' . $data['arquivo']);
                    }
                    $resultado = TransportadoraTaxaImportService::importarTaxas(
                        $path,
                        $this->record->id_transportadora,
                        $data['limpar_antes'] ?? false
                    );
                    @unlink($path);
                    $msg = "{$resultado['importados']} taxa(s) importada(s)";
                    if ($resultado['erros'] > 0) $msg .= ", {$resultado['erros']} erro(s)";
                    if (!empty($resultado['mensagens'])) $msg .= '. ' . implode('. ', $resultado['mensagens']);
                    Notification::make()->title($msg)
                        ->color($resultado['erros'] > 0 ? 'warning' : 'success')
                        ->send();
                }),
        ];
    }

    protected function afterSave(): void
    {
        $ufs = $this->data['ufs_selecionadas'] ?? [];
        TransportadoraUf::where('id_transportadora', $this->record->id_transportadora)->delete();
        foreach ($ufs as $uf) {
            TransportadoraUf::create([
                'id_transportadora' => $this->record->id_transportadora,
                'uf' => $uf,
            ]);
        }
    }
}

<?php

namespace App\Filament\Resources\TransportadoraResource\Pages;

use App\Filament\Resources\TransportadoraResource;
use App\Models\TransportadoraTabelaFrete;
use App\Models\TransportadoraTaxa;
use App\Models\TransportadoraUf;
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

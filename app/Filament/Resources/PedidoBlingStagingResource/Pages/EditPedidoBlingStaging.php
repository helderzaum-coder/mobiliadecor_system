<?php

namespace App\Filament\Resources\PedidoBlingStagingResource\Pages;

use App\Filament\Resources\PedidoBlingStagingResource;
use App\Services\AprovacaoVendaService;
use App\Services\Bling\BlingImportService;
use App\Services\CteService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPedidoBlingStaging extends EditRecord
{
    protected static string $resource = PedidoBlingStagingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('buscar_nfe')
                ->label('Buscar NF-e')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('info')
                ->requiresConfirmation()
                ->action(function () {
                    $found = BlingImportService::buscarNfePorPedido($this->record);
                    if ($found) {
                        Notification::make()->title('NF-e encontrada e vinculada.')->success()->send();
                        $this->fillForm();
                    } else {
                        Notification::make()->title('NF-e não encontrada.')->warning()->send();
                    }
                })
                ->visible(fn () => $this->record->status === 'pendente' && empty($this->record->nfe_chave_acesso)),
            Actions\Action::make('buscar_custos')
                ->label('Buscar Custos')
                ->icon('heroicon-o-calculator')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    $qtd = BlingImportService::buscarCustosProdutos($this->record);
                    if ($qtd > 0) {
                        Notification::make()->title("{$qtd} custo(s) atualizado(s).")->success()->send();
                        $this->fillForm();
                    } else {
                        Notification::make()->title('Nenhum custo encontrado.')->warning()->send();
                    }
                })
                ->visible(fn () => $this->record->status === 'pendente'),
            Actions\Action::make('buscar_cte')
                ->label('Buscar CT-e')
                ->icon('heroicon-o-truck')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Buscar CT-e de Frete')
                ->modalDescription('Busca o XML do CT-e na pasta pendentes pela chave da NF-e. Atualiza o custo do frete e move o XML para processados.')
                ->action(function () {
                    $result = CteService::processarCte($this->record);
                    if ($result['success']) {
                        Notification::make()->title($result['msg'])->success()->send();
                        $this->fillForm();
                    } else {
                        Notification::make()->title($result['msg'])->warning()->send();
                    }
                })
                ->visible(fn () => $this->record->status === 'pendente' && !empty($this->record->nfe_chave_acesso)),
            Actions\Action::make('aprovar')
                ->label('Aprovar e Salvar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    if (PedidoBlingStagingResource::isShopee($this->record) && !$this->record->planilha_shopee) {
                        Notification::make()->title('Processe a planilha Shopee antes de aprovar.')->danger()->send();
                        return;
                    }
                    $this->save();
                    AprovacaoVendaService::aprovar($this->record);
                    Notification::make()->title('Pedido aprovado e venda criada.')->success()->send();
                    $this->redirect(PedidoBlingStagingResource::getUrl());
                }),
        ];
    }
}

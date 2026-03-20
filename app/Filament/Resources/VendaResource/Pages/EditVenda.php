<?php

namespace App\Filament\Resources\VendaResource\Pages;

use App\Filament\Resources\VendaResource;
use App\Models\CanalVenda;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditVenda extends EditRecord
{
    protected static string $resource = VendaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('recalcular')
                ->label('Recalcular Margens')
                ->icon('heroicon-o-calculator')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Recalcular Margens')
                ->modalDescription('Isso vai recalcular todas as margens com base nos valores atuais do formulário.')
                ->action(function () {
                    $venda = $this->record;
                    $canal = CanalVenda::find($venda->id_canal);

                    $totalProdutos = (float) $venda->total_produtos;
                    $frete = (float) $venda->valor_frete_cliente;
                    $custoFrete = (float) $venda->valor_frete_transportadora;
                    $comissao = (float) $venda->comissao;
                    $subsidioPix = (float) $venda->subsidio_pix;
                    $valorImposto = (float) $venda->valor_imposto;
                    $totalPedido = (float) $venda->valor_total_venda;
                    $valorRebate = (float) ($venda->ml_valor_rebate ?? 0);
                    $percentualImposto = (float) $venda->percentual_imposto;
                    $custoProdutos = (float) $venda->custo_produtos;
                    $comissaoSobreFrete = (bool) ($canal->comissao_sobre_frete ?? false);
                    $impostoSobreFrete = (bool) ($canal->imposto_sobre_frete ?? false);

                    // Comissão sobre frete
                    $comissaoFrete = 0;
                    if ($comissaoSobreFrete && $frete > 0 && $canal) {
                        $regra = $canal->regrasComissao()->where('ativo', true)->first();
                        if ($regra) {
                            $comissaoFrete = round($frete * (float) $regra->percentual / 100, 2);
                        }
                    }

                    // Imposto sobre frete
                    $impostoFrete = 0;
                    if ($impostoSobreFrete && $frete > 0 && $percentualImposto > 0) {
                        $impostoFrete = round($frete * $percentualImposto / 100, 2);
                    }

                    $impostoProduto = $valorImposto - $impostoFrete;
                    $margemFrete = $frete - $custoFrete - $comissaoFrete - $impostoFrete;
                    $comissaoProduto = $comissao - $comissaoFrete;
                    $margemProduto = $totalProdutos - $custoProdutos - $comissaoProduto - $impostoProduto + $valorRebate;
                    $margemVendaTotal = $margemProduto + $margemFrete + $subsidioPix;
                    $margemContribuicao = $totalPedido > 0
                        ? round(($margemVendaTotal / $totalPedido) * 100, 2)
                        : 0;

                    $venda->update([
                        'margem_frete' => round($margemFrete, 2),
                        'margem_produto' => round($margemProduto, 2),
                        'margem_venda_total' => round($margemVendaTotal, 2),
                        'margem_contribuicao' => round($margemContribuicao, 2),
                    ]);

                    Notification::make()
                        ->title('Margens recalculadas')
                        ->success()
                        ->send();

                    $this->fillForm();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}

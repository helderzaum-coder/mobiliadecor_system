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

    protected function afterSave(): void
    {
        // Recalcular margens automaticamente ao salvar
        \App\Services\VendaRecalculoService::recalcularMargens($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('recalcular')
                ->label('Recalcular Margens')
                ->icon('heroicon-o-calculator')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Recalcular Margens')
                ->modalDescription('Recalcula margens com base nos valores atuais. Para ML, usa dados reais da API (sale_fee + taxa frete).')
                ->action(function () {
                    $venda = $this->record;
                    $canal = CanalVenda::find($venda->id_canal);
                    $nomeCanal = $canal->nome_canal ?? '';

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

                    // ML: usar dados reais da API
                    $isML = str_contains(strtolower($nomeCanal), 'mercado')
                        || str_starts_with($venda->numero_pedido_canal ?? '', '2000');
                    $mlSaleFee = (float) ($venda->ml_sale_fee ?? 0);
                    $mlFreteCusto = (float) ($venda->ml_frete_custo ?? 0);
                    $mlFreteReceita = (float) ($venda->ml_frete_receita ?? 0);
                    $tipoFrete = $venda->ml_tipo_frete ?? null;

                    if ($isML) {
                        if ($tipoFrete === 'ME2' || $tipoFrete === 'FULL') {
                            $taxaFreteML = $mlFreteCusto > 0 ? ($mlFreteCusto - $mlFreteReceita) : 0;
                            if ($mlSaleFee > 0) {
                                $comissao = $mlSaleFee + $taxaFreteML;
                                $valorRebate = 0; // sale_fee da API já tem rebate descontado
                            }
                            $frete = 0;
                            $custoFrete = 0;
                        } else {
                            if ($mlFreteReceita > 0) $frete = $mlFreteReceita;
                            if ($mlFreteCusto > 0) $custoFrete = $mlFreteCusto;
                            if ($mlSaleFee > 0) {
                                $comissao = $mlSaleFee;
                                $valorRebate = 0;
                            }
                        }
                    }

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
                        ? round(($margemVendaTotal / $totalPedido) * 100, 2) : 0;

                    $updateData = [
                        'comissao' => round($comissao, 2),
                        'valor_frete_cliente' => round($frete, 2),
                        'valor_frete_transportadora' => round($custoFrete, 2),
                        'margem_frete' => round($margemFrete, 2),
                        'margem_produto' => round($margemProduto, 2),
                        'margem_venda_total' => round($margemVendaTotal, 2),
                        'margem_contribuicao' => round($margemContribuicao, 2),
                    ];

                    $venda->update($updateData);

                    Notification::make()
                        ->title('Margens recalculadas')
                        ->body("Comissão: R$ " . number_format($comissao, 2, ',', '.') . " | Lucro: R$ " . number_format($margemVendaTotal, 2, ',', '.'))
                        ->success()
                        ->send();

                    $this->fillForm();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}

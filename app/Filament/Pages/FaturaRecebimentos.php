<?php

namespace App\Filament\Pages;

use App\Models\CanalVenda;
use App\Models\ContaBancaria;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\FaturaRecebimento;
use App\Models\LoteRecebimento;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class FaturaRecebimentos extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Financeiro';
    protected static ?string $navigationLabel = 'Faturas de Recebimento';
    protected static ?string $title = 'Faturas de Recebimento';
    protected static string $view = 'filament.pages.fatura-recebimentos';
    protected static ?int $navigationSort = 4;

    // Listagem
    public string $filtro_status = 'aberta';

    // Formulário nova fatura
    public bool $criando = false;
    public ?int $editando_id = null;
    public string $form_canal_id = '';
    public string $form_descricao = '';
    public string $form_data_prevista = '';
    public string $form_conta_bancaria_id = '';

    // Busca de pedidos
    public string $busca = '';
    public array $contas_selecionadas = [];

    // Ajustes (igual ao Lote)
    public array $descontos = [];
    public string $desconto_descricao = '';
    public string $desconto_valor = '';
    public string $busca_reembolso = '';
    public array $entradas_avulsas = [];
    public string $entrada_descricao = '';
    public string $entrada_valor = '';
    public string $entrada_canal = '';

    public function mount(): void
    {
        $this->form_data_prevista = now()->addDays(10)->format('Y-m-d');
    }

    public function getFaturasProperty()
    {
        return FaturaRecebimento::with(['canal', 'contaBancaria'])
            ->where('status', $this->filtro_status)
            ->withCount('contasReceber')
            ->orderByDesc('data_prevista')
            ->get();
    }

    public function getCanaisProperty()
    {
        return CanalVenda::where('ativo', true)->orderBy('nome_canal')->get();
    }

    public function getContasBancariasProperty()
    {
        return ContaBancaria::where('ativo', true)->orderBy('nome')->get();
    }

    public function getResultadosBuscaProperty()
    {
        if (strlen($this->busca) < 3) return collect();

        $idsJaSelecionados = $this->contas_selecionadas;

        return ContaReceber::with('venda.canal')
            ->where('status', 'pendente')
            ->whereNull('fatura_recebimento_id')
            ->whereNull('lote_recebimento_id')
            ->where(function ($q) {
                $q->whereHas('venda', fn ($q2) => $q2->where('numero_pedido_canal', 'like', '%' . $this->busca . '%'))
                  ->orWhere('observacoes', 'like', '%' . $this->busca . '%');
            })
            ->when(!empty($this->form_canal_id), function ($q) {
                $q->whereHas('venda', fn ($q2) => $q2->where('id_canal', $this->form_canal_id));
            })
            ->whereNotIn('id_conta_receber', $idsJaSelecionados)
            ->orderBy('numero_parcela')
            ->limit(20)
            ->get();
    }

    public function getContasSelecionadasItensProperty()
    {
        if (empty($this->contas_selecionadas)) return collect();
        return ContaReceber::with('venda.canal')
            ->whereIn('id_conta_receber', $this->contas_selecionadas)
            ->get();
    }

    public function getTotalContasProperty(): float
    {
        return $this->contasSelecionadasItens->sum('valor_parcela');
    }

    public function getTotalDescontosProperty(): float
    {
        return collect($this->descontos)->sum('valor');
    }

    public function getTotalEntradasAvulsasProperty(): float
    {
        return collect($this->entradas_avulsas)->sum('valor');
    }

    public function getTotalFaturaProperty(): float
    {
        return $this->totalContas + $this->totalEntradasAvulsas - $this->totalDescontos;
    }

    public function adicionarConta(int $id): void
    {
        if (!in_array($id, $this->contas_selecionadas)) {
            $this->contas_selecionadas[] = $id;
        }
        $this->busca = '';
    }

    public function removerConta(int $id): void
    {
        $this->contas_selecionadas = array_values(array_filter($this->contas_selecionadas, fn ($i) => $i !== $id));
    }

    public function adicionarDesconto(): void
    {
        if (empty(trim($this->desconto_descricao)) || (float) $this->desconto_valor <= 0) {
            Notification::make()->title('Informe descrição e valor do desconto.')->warning()->send();
            return;
        }
        $this->descontos[] = [
            'descricao' => trim($this->desconto_descricao),
            'valor' => round((float) $this->desconto_valor, 2),
            'conta_pagar_id' => null,
        ];
        $this->desconto_descricao = '';
        $this->desconto_valor = '';
    }

    public function removerDesconto(int $index): void
    {
        unset($this->descontos[$index]);
        $this->descontos = array_values($this->descontos);
    }

    public function buscarReembolso(): void
    {
        if (empty(trim($this->busca_reembolso))) return;

        $contaPagar = ContaPagar::where('status', 'pendente')
            ->whereIn('forma_pagamento', ['Estorno', 'Reembolso'])
            ->where('observacoes', 'like', '%' . trim($this->busca_reembolso) . '%')
            ->first();

        if (!$contaPagar) {
            Notification::make()->title('Nenhum reembolso/estorno pendente encontrado.')->warning()->send();
            return;
        }

        foreach ($this->descontos as $d) {
            if (($d['conta_pagar_id'] ?? null) == $contaPagar->id_conta_pagar) {
                Notification::make()->title('Este reembolso já está na fatura.')->warning()->send();
                return;
            }
        }

        $this->descontos[] = [
            'descricao' => "🔄 {$contaPagar->forma_pagamento} - Pedido #{$this->busca_reembolso}",
            'valor' => round((float) $contaPagar->valor_parcela, 2),
            'conta_pagar_id' => $contaPagar->id_conta_pagar,
        ];

        Notification::make()->title("Reembolso de R$ " . number_format($contaPagar->valor_parcela, 2, ',', '.') . " adicionado.")->success()->send();
        $this->busca_reembolso = '';
    }

    public function adicionarEntradaAvulsa(): void
    {
        if (empty(trim($this->entrada_descricao)) || (float) $this->entrada_valor <= 0) {
            Notification::make()->title('Informe descrição e valor da entrada.')->warning()->send();
            return;
        }
        $this->entradas_avulsas[] = [
            'descricao' => trim($this->entrada_descricao),
            'valor' => round((float) $this->entrada_valor, 2),
            'canal' => trim($this->entrada_canal) ?: 'Entrada Avulsa',
        ];
        $this->entrada_descricao = '';
        $this->entrada_valor = '';
        $this->entrada_canal = '';
    }

    public function removerEntradaAvulsa(int $index): void
    {
        unset($this->entradas_avulsas[$index]);
        $this->entradas_avulsas = array_values($this->entradas_avulsas);
    }

    public function iniciarCriacao(): void
    {
        $this->resetFormulario();
        $this->criando = true;
        $this->editando_id = null;
    }

    public function editarFatura(int $id): void
    {
        $fatura = FaturaRecebimento::findOrFail($id);
        $this->editando_id = $id;
        $this->criando = true;
        $this->form_canal_id = (string) ($fatura->canal_id ?? '');
        $this->form_descricao = $fatura->descricao ?? '';
        $this->form_data_prevista = $fatura->data_prevista->format('Y-m-d');
        $this->form_conta_bancaria_id = (string) ($fatura->conta_bancaria_id ?? '');
        $this->descontos = $fatura->descontos ?? [];
        $this->entradas_avulsas = $fatura->entradas_avulsas ?? [];
        $this->contas_selecionadas = $fatura->contasReceber()->pluck('id_conta_receber')->toArray();
    }

    public function salvarFatura(): void
    {
        if (empty($this->form_data_prevista)) {
            Notification::make()->title('Informe a data prevista.')->warning()->send();
            return;
        }
        if (empty($this->contas_selecionadas)) {
            Notification::make()->title('Adicione ao menos um pedido.')->warning()->send();
            return;
        }

        $valorTotal = round($this->totalFatura, 2);

        if ($this->editando_id) {
            $fatura = FaturaRecebimento::findOrFail($this->editando_id);

            // Desvincular contas antigas que foram removidas
            $fatura->contasReceber()->whereNotIn('id_conta_receber', $this->contas_selecionadas)
                ->update(['fatura_recebimento_id' => null]);

            $fatura->update([
                'canal_id' => $this->form_canal_id ?: null,
                'descricao' => $this->form_descricao ?: null,
                'data_prevista' => $this->form_data_prevista,
                'conta_bancaria_id' => $this->form_conta_bancaria_id ?: null,
                'valor_total' => $valorTotal,
                'descontos' => $this->descontos,
                'entradas_avulsas' => $this->entradas_avulsas,
            ]);
        } else {
            $fatura = FaturaRecebimento::create([
                'canal_id' => $this->form_canal_id ?: null,
                'descricao' => $this->form_descricao ?: null,
                'data_prevista' => $this->form_data_prevista,
                'conta_bancaria_id' => $this->form_conta_bancaria_id ?: null,
                'status' => 'aberta',
                'valor_total' => $valorTotal,
                'descontos' => $this->descontos,
                'entradas_avulsas' => $this->entradas_avulsas,
            ]);
        }

        // Vincular contas selecionadas
        ContaReceber::whereIn('id_conta_receber', $this->contas_selecionadas)
            ->update(['fatura_recebimento_id' => $fatura->id]);

        Notification::make()->title('Fatura salva com sucesso.')->success()->send();
        $this->resetFormulario();
        $this->criando = false;
    }

    public function confirmarFatura(int $id): void
    {
        $fatura = FaturaRecebimento::with('contasReceber.venda')->findOrFail($id);

        if ($fatura->status !== 'aberta') {
            Notification::make()->title('Fatura já foi confirmada ou cancelada.')->warning()->send();
            return;
        }

        $dataRecebimento = $fatura->data_prevista->format('Y-m-d');
        $count = 0;
        $valorTotal = 0;

        foreach ($fatura->contasReceber as $conta) {
            if ($conta->status !== 'pendente') continue;

            $conta->update([
                'status' => 'recebido',
                'data_recebimento' => $dataRecebimento,
                'conta_bancaria_id' => $fatura->conta_bancaria_id,
            ]);

            if ($conta->venda) {
                $pendentes = ContaReceber::where('id_venda', $conta->id_venda)
                    ->where('status', 'pendente')->count();
                if ($pendentes === 0) {
                    $conta->venda->update([
                        'repasse_recebido' => true,
                        'data_recebimento' => $dataRecebimento,
                    ]);
                }
            }

            $valorTotal += (float) $conta->valor_parcela;
            $count++;
        }

        // Criar LoteRecebimento
        $descricao = $fatura->descricao
            ?: LoteRecebimento::gerarDescricao(
                $fatura->contaBancaria?->nome,
                $dataRecebimento,
                $fatura->canal?->nome_canal
            );

        $descontos = $fatura->descontos ?? [];
        $entradas = $fatura->entradas_avulsas ?? [];
        $totalDescontos = collect($descontos)->sum('valor');
        $totalEntradas = collect($entradas)->sum('valor');

        $lote = LoteRecebimento::create([
            'data_recebimento' => $dataRecebimento,
            'descricao' => $descricao,
            'valor_total' => round($valorTotal + $totalEntradas - $totalDescontos, 2),
            'quantidade_contas' => $count,
        ]);

        // Vincular contas ao lote
        ContaReceber::whereIn('id_conta_receber', $fatura->contasReceber->pluck('id_conta_receber'))
            ->update(['lote_recebimento_id' => $lote->id]);

        // Processar descontos
        foreach ($descontos as $desconto) {
            if (!empty($desconto['conta_pagar_id'])) {
                ContaPagar::where('id_conta_pagar', $desconto['conta_pagar_id'])->update([
                    'status' => 'pago',
                    'data_pagamento' => $dataRecebimento,
                    'conta_bancaria_id' => $fatura->conta_bancaria_id,
                    'lote_recebimento_id' => $lote->id,
                ]);
            } else {
                ContaPagar::create([
                    'valor_parcela' => $desconto['valor'],
                    'data_vencimento' => $dataRecebimento,
                    'data_pagamento' => $dataRecebimento,
                    'status' => 'pago',
                    'numero_parcela' => 1,
                    'total_parcelas' => 1,
                    'forma_pagamento' => 'Desconto Canal',
                    'observacoes' => $desconto['descricao'],
                    'lancamento_manual' => true,
                    'conta_bancaria_id' => $fatura->conta_bancaria_id,
                    'lote_recebimento_id' => $lote->id,
                ]);
            }
        }

        // Processar entradas avulsas
        foreach ($entradas as $entrada) {
            ContaReceber::create([
                'valor_parcela' => $entrada['valor'],
                'data_vencimento' => $dataRecebimento,
                'data_recebimento' => $dataRecebimento,
                'status' => 'recebido',
                'numero_parcela' => 1,
                'total_parcelas' => 1,
                'forma_pagamento' => $entrada['canal'] ?? 'Entrada Avulsa',
                'observacoes' => $entrada['descricao'],
                'lancamento_manual' => true,
                'conta_bancaria_id' => $fatura->conta_bancaria_id,
                'lote_recebimento_id' => $lote->id,
            ]);
        }

        $fatura->update([
            'status' => 'confirmada',
            'lote_recebimento_id' => $lote->id,
        ]);

        Notification::make()->title("{$count} recebimento(s) confirmados. Lote #{$lote->id} criado.")->success()->send();
    }

    public function cancelarFatura(int $id): void
    {
        $fatura = FaturaRecebimento::findOrFail($id);
        $fatura->contasReceber()->update(['fatura_recebimento_id' => null]);
        $fatura->update(['status' => 'cancelada']);
        Notification::make()->title('Fatura cancelada. Pedidos liberados.')->success()->send();
    }

    public function cancelarFormulario(): void
    {
        $this->resetFormulario();
        $this->criando = false;
    }

    private function resetFormulario(): void
    {
        $this->form_canal_id = '';
        $this->form_descricao = '';
        $this->form_data_prevista = now()->addDays(10)->format('Y-m-d');
        $this->form_conta_bancaria_id = '';
        $this->contas_selecionadas = [];
        $this->descontos = [];
        $this->entradas_avulsas = [];
        $this->desconto_descricao = '';
        $this->desconto_valor = '';
        $this->entrada_descricao = '';
        $this->entrada_valor = '';
        $this->entrada_canal = '';
        $this->busca = '';
        $this->editando_id = null;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}

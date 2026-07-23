<?php

namespace App\Filament\Pages;

use App\Models\ProdutoEstoque;
use App\Models\TrocaTampoConfig;
use App\Jobs\VariacaoTamposJob;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Actions\Action;

class LancamentoCarcacas extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';
    protected static ?string $navigationGroup = 'Estoque';
    protected static ?string $navigationLabel = 'Lançar Carcaças';
    protected static ?string $title = 'Lançamento de Carcaças';
    protected static string $view = 'filament.pages.lancamento-carcacas';

    public ?string $sku_selecionado = null;
    public int $quantidade = 1;
    public array $grupos = [];

    public function mount(): void
    {
        $this->carregarGrupos();
        $this->form->fill();
    }

    public function carregarGrupos(): void
    {
        $configs = TrocaTampoConfig::where('equalizacao_ativa', true)
            ->orderBy('grupo')->orderBy('cor')->orderBy('tipo_tampo')
            ->get();

        $skusProduto = $configs->pluck('sku_produto')->unique();
        $produtos = ProdutoEstoque::whereIn('sku', $skusProduto)
            ->where('ativo', true)
            ->get()
            ->keyBy('sku');

        $grupos = [];
        foreach ($configs->groupBy(fn ($c) => $c->grupo . '||' . $c->cor) as $chave => $membros) {
            [$grupo, $cor] = explode('||', $chave);

            $totalCarcacas = 0;
            $itens = [];
            foreach ($membros as $config) {
                $produto = $produtos->get($config->sku_produto);
                $carcaca = $produto ? (int) $produto->saldo_carcaca : 0;
                $totalCarcacas += $carcaca;
                $itens[] = [
                    'sku'           => $config->sku_produto,
                    'nome'          => $config->nome_produto,
                    'tipo_tampo'    => $config->tipo_tampo,
                    'saldo_fisico'  => $produto ? (int) $produto->saldo_fisico : 0,
                    'saldo_carcaca' => $produto ? $produto->saldo_carcaca : null,
                ];
            }

            $grupos[] = [
                'grupo'          => $grupo,
                'cor'            => $cor,
                'chave'          => $chave,
                'total_carcacas' => $totalCarcacas,
                'itens'          => $itens,
            ];
        }

        $this->grupos = $grupos;
    }

    public function form(Form $form): Form
    {
        $opcoes = [];
        $configs = TrocaTampoConfig::where('equalizacao_ativa', true)
            ->orderBy('grupo')->orderBy('cor')->orderBy('tipo_tampo')
            ->get();

        foreach ($configs as $config) {
            $produto = ProdutoEstoque::where('sku', $config->sku_produto)->where('ativo', true)->first();
            $carcaca = $produto ? (int) $produto->saldo_carcaca : 0;
            $opcoes[$config->sku_produto] = "{$config->nome_produto} — Carc. atual: {$carcaca}";
        }

        return $form->schema([
            Forms\Components\Select::make('sku_selecionado')
                ->label('SKU que chegou da fábrica')
                ->options($opcoes)
                ->searchable()
                ->required()
                ->helperText('Selecione o SKU exato que consta na nota fiscal / caixa recebida'),
            Forms\Components\TextInput::make('quantidade')
                ->label('Quantidade recebida')
                ->numeric()
                ->required()
                ->minValue(1)
                ->default(1),
        ]);
    }

    public function lancar(): void
    {
        $this->validate([
            'sku_selecionado' => 'required',
            'quantidade'      => 'required|integer|min:1',
        ]);

        $produto = ProdutoEstoque::where('sku', $this->sku_selecionado)->where('ativo', true)->first();

        if (!$produto) {
            Notification::make()->title('Produto não encontrado.')->danger()->send();
            return;
        }

        $anterior = (int) $produto->saldo_carcaca;
        $novo = $anterior + $this->quantidade;
        $produto->update(['saldo_carcaca' => $novo]);

        // Disparar equalização para refletir no estoque físico
        $config = TrocaTampoConfig::where('sku_produto', $this->sku_selecionado)
            ->whereNotNull('familia_tampo')
            ->where('familia_tampo', '!=', '')
            ->first();

        if ($config) {
            VariacaoTamposJob::dispatch('primary', $config->familia_tampo);
        }

        Notification::make()
            ->title("Carcaças lançadas: {$produto->sku}")
            ->body("{$anterior} → {$novo} (+{$this->quantidade}). Equalização disparada.")
            ->success()
            ->send();

        $this->quantidade = 1;
        $this->sku_selecionado = null;
        $this->carregarGrupos();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recarregar')
                ->label('Recarregar')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->carregarGrupos()),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'tampo']) ?? false;
    }
}

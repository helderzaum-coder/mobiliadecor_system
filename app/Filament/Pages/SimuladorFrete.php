<?php

namespace App\Filament\Pages;

use App\Services\CotacaoFreteService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class SimuladorFrete extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationGroup = 'Cadastros';
    protected static ?string $navigationLabel = 'Simulador de Frete';
    protected static ?string $title = 'Simulador de Frete';
    protected static string $view = 'filament.pages.simulador-frete';

    public ?array $data = [];
    public array $cotacoes = [];
    public bool $simulado = false;
    public float $pesoCubado = 0;
    public float $pesoUsado = 0;
    public string $pesoTipo = '';

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(4)->schema([
                Forms\Components\TextInput::make('dest_cep')
                    ->label('CEP Destino')
                    ->required()
                    ->mask('99999-999')
                    ->placeholder('00000-000'),
                Forms\Components\TextInput::make('dest_uf')
                    ->label('UF')
                    ->required()
                    ->maxLength(2)
                    ->placeholder('SP')
                    ->extraInputAttributes(['style' => 'text-transform:uppercase']),
                Forms\Components\TextInput::make('dest_cidade')
                    ->label('Cidade (opcional)')
                    ->placeholder('São Paulo'),
                Forms\Components\TextInput::make('valor_nf')
                    ->label('Valor NF (R$)')
                    ->required()
                    ->numeric()
                    ->prefix('R$')
                    ->placeholder('0,00'),
            ]),
            Forms\Components\Grid::make(4)->schema([
                Forms\Components\TextInput::make('peso_bruto')
                    ->label('Peso Bruto (kg)')
                    ->required()
                    ->numeric()
                    ->suffix('kg')
                    ->placeholder('0,00'),
                Forms\Components\TextInput::make('largura')
                    ->label('Largura (cm)')
                    ->numeric()
                    ->suffix('cm')
                    ->placeholder('0'),
                Forms\Components\TextInput::make('altura')
                    ->label('Altura (cm)')
                    ->numeric()
                    ->suffix('cm')
                    ->placeholder('0'),
                Forms\Components\TextInput::make('comprimento')
                    ->label('Comprimento (cm)')
                    ->numeric()
                    ->suffix('cm')
                    ->placeholder('0'),
            ]),
        ])->statePath('data');
    }

    public function simular(): void
    {
        $data = $this->form->getState();

        $cep    = preg_replace('/\D/', '', $data['dest_cep'] ?? '');
        $uf     = strtoupper(trim($data['dest_uf'] ?? ''));
        $pesoBruto = (float) ($data['peso_bruto'] ?? 0);

        // Peso cubado: (C × L × A em metros) × 300
        $largura     = (float) ($data['largura'] ?? 0) / 100;
        $altura      = (float) ($data['altura'] ?? 0) / 100;
        $comprimento = (float) ($data['comprimento'] ?? 0) / 100;

        $this->pesoCubado = ($largura > 0 && $altura > 0 && $comprimento > 0)
            ? round($comprimento * $largura * $altura * 300, 3)
            : 0;

        $this->pesoUsado = max($pesoBruto, $this->pesoCubado);
        $this->pesoTipo  = ($this->pesoCubado > $pesoBruto) ? 'cubado' : 'real';

        $this->cotacoes = CotacaoFreteService::cotar(
            destUf: $uf,
            destCep: $cep,
            pesoBruto: $this->pesoUsado,
            valorNf: (float) ($data['valor_nf'] ?? 0),
            destCidade: trim($data['dest_cidade'] ?? '') ?: null,
        );

        $this->simulado = true;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}

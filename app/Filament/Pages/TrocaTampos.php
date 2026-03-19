<?php

namespace App\Filament\Pages;

use App\Models\TrocaTampoConfig;
use App\Services\TrocaTampoService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class TrocaTampos extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = 'Operações';
    protected static ?string $navigationLabel = 'Troca de Tampos';
    protected static ?string $title = 'Troca de Tampos';
    protected static string $view = 'filament.pages.troca-tampos';

    // Passo 1: Produto vendido
    public ?string $grupo = null;
    public ?string $cor = null;
    public ?string $tipo_tampo = null;

    // Passo 2: Fonte do tampo
    public ?string $fonte_tampo = null;

    // Passo 3: Caixa a abrir (fornece carcaça)
    public ?int $caixa_aberta_id = null;

    public bool $executado = false;
    public array $resultado = [];

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('1. Produto Vendido')->schema([
                Forms\Components\Select::make('grupo')
                    ->label('Grupo')
                    ->options(fn () => TrocaTampoService::getGrupos())
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn ($set) => $this->resetFrom('grupo', $set)),

                Forms\Components\Select::make('cor')
                    ->label('Cor')
                    ->options(fn ($get) => $get('grupo') ? TrocaTampoService::getCores($get('grupo')) : [])
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn ($set) => $this->resetFrom('cor', $set))
                    ->visible(fn ($get) => filled($get('grupo'))),

                Forms\Components\Select::make('tipo_tampo')
                    ->label('Tipo de Tampo')
                    ->options(fn ($get) => ($get('grupo') && $get('cor'))
                        ? TrocaTampoService::getTiposTampo($get('grupo'), $get('cor'))
                        : [])
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn ($set) => $this->resetFrom('tipo_tampo', $set))
                    ->visible(fn ($get) => filled($get('cor')))
                    ->helperText(function ($get) {
                        if (!$get('grupo') || !$get('cor') || !$get('tipo_tampo')) return null;
                        $p = TrocaTampoService::getProdutoVendido($get('grupo'), $get('cor'), $get('tipo_tampo'));
                        return $p ? "→ {$p->nome_produto} (SKU: {$p->sku_produto})" : null;
                    }),
            ])->columns(3),

            Forms\Components\Section::make('2. Fonte do Tampo')->schema([
                Forms\Components\Select::make('fonte_tampo')
                    ->label('De onde vem o tampo?')
                    ->options(fn ($get) => ($get('grupo') && $get('cor') && $get('tipo_tampo'))
                        ? TrocaTampoService::getFontesTampo($get('grupo'), $get('cor'), $get('tipo_tampo'))
                        : [])
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn ($set) => $set('caixa_aberta_id', null))
                    ->helperText('Selecione se o tampo vem do estoque avulso ou de outra caixa'),
            ])->visible(fn ($get) => filled($get('tipo_tampo'))),

            Forms\Components\Section::make('3. Caixa a Abrir (fornece a carcaça)')->schema([
                Forms\Components\Select::make('caixa_aberta_id')
                    ->label('Qual caixa abrir?')
                    ->options(fn ($get) => ($get('grupo') && $get('cor') && $get('tipo_tampo'))
                        ? TrocaTampoService::getCaixasParaCarcaca($get('grupo'), $get('cor'), $get('tipo_tampo'))
                        : [])
                    ->required()
                    ->reactive()
                    ->helperText(function ($get) {
                        if (!$get('caixa_aberta_id') || !$get('fonte_tampo')) return null;
                        if ($get('fonte_tampo') === 'estoque') {
                            return 'A carcaça desta caixa será usada para montar o produto vendido. O tampo dela volta pro estoque.';
                        }
                        // Fonte é outra caixa — mostrar o que será montado com a carcaça da fonte
                        $caixaAberta = TrocaTampoConfig::find($get('caixa_aberta_id'));
                        $fonteId = str_replace('caixa_', '', $get('fonte_tampo'));
                        $fonte = TrocaTampoConfig::find($fonteId);
                        if ($caixaAberta && $fonte) {
                            $destino = TrocaTampoConfig::where('grupo', $fonte->grupo)
                                ->where('cor', $fonte->cor)
                                ->where('tipo_tampo', $caixaAberta->tipo_tampo)
                                ->first();
                            if ($destino) {
                                return "Carcaça da fonte ({$fonte->nome_produto}) + tampo desta caixa → {$destino->nome_produto}";
                            }
                        }
                        return null;
                    }),
            ])->visible(fn ($get) => filled($get('fonte_tampo'))),
        ]);
    }

    private function resetFrom(string $field, $set): void
    {
        $fields = ['cor', 'tipo_tampo', 'fonte_tampo', 'caixa_aberta_id'];
        $start = array_search($field, $fields);
        if ($start !== false) {
            for ($i = $start; $i < count($fields); $i++) {
                $set($fields[$i], null);
            }
        }
    }

    public function executar(): void
    {
        $data = $this->form->getState();

        $produtoVendido = TrocaTampoService::getProdutoVendido($data['grupo'], $data['cor'], $data['tipo_tampo']);

        if (!$produtoVendido) {
            Notification::make()->title('Produto vendido não encontrado na configuração.')->danger()->send();
            return;
        }

        $service = new TrocaTampoService('primary');
        $this->resultado = $service->executarTroca(
            $produtoVendido->id,
            (int) $data['caixa_aberta_id'],
            $data['fonte_tampo']
        );
        $this->executado = true;

        if ($this->resultado['success']) {
            Notification::make()->title('Troca realizada com sucesso.')->success()->send();
        } else {
            $erroMsg = implode(' | ', $this->resultado['erros'] ?? ['Erro desconhecido']);
            Notification::make()->title('Troca com erros: ' . $erroMsg)->danger()->send();
        }
    }

    public function limpar(): void
    {
        $this->executado = false;
        $this->resultado = [];
        $this->grupo = null;
        $this->cor = null;
        $this->tipo_tampo = null;
        $this->fonte_tampo = null;
        $this->caixa_aberta_id = null;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}

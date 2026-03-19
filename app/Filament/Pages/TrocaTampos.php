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
use Illuminate\Support\HtmlString;

class TrocaTampos extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = 'Operações';
    protected static ?string $navigationLabel = 'Troca de Tampos';
    protected static ?string $title = 'Troca de Tampos';
    protected static string $view = 'filament.pages.troca-tampos';

    public ?string $bling_account = 'primary';
    public ?string $grupo = null;
    public ?string $cor = null;
    public ?int $caixa_aberta_id = null;
    public ?int $tampo_usado_id = null;
    public ?string $destino_tampo = null;

    public bool $executado = false;
    public array $resultado = [];

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Operação de Troca')->schema([
                Forms\Components\Select::make('grupo')
                    ->label('Grupo de Produto')
                    ->options(fn () => TrocaTampoService::getGrupos())
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn ($set) => $set('cor', null)),

                Forms\Components\Select::make('cor')
                    ->label('Cor')
                    ->options(fn ($get) => $get('grupo') ? TrocaTampoService::getCores($get('grupo')) : [])
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($set) {
                        $set('caixa_aberta_id', null);
                        $set('tampo_usado_id', null);
                        $set('destino_tampo', null);
                    })
                    ->visible(fn ($get) => filled($get('grupo'))),

                Forms\Components\Select::make('caixa_aberta_id')
                    ->label('Caixa a ser aberta (produto que será desmontado)')
                    ->options(fn ($get) => ($get('grupo') && $get('cor'))
                        ? TrocaTampoService::getProdutos($get('grupo'), $get('cor'))
                        : [])
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn ($set) => $set('destino_tampo', null))
                    ->helperText('Selecione o produto cuja caixa será aberta')
                    ->visible(fn ($get) => filled($get('cor'))),

                Forms\Components\Select::make('tampo_usado_id')
                    ->label('Produto a ser montado (tampo que será colocado)')
                    ->options(function ($get) {
                        if (!$get('grupo') || !$get('cor')) return [];
                        $produtos = TrocaTampoService::getProdutos($get('grupo'), $get('cor'));
                        // Remover a caixa aberta das opções
                        unset($produtos[$get('caixa_aberta_id')]);
                        return $produtos;
                    })
                    ->required()
                    ->reactive()
                    ->helperText('Selecione o produto final que você quer montar')
                    ->visible(fn ($get) => filled($get('caixa_aberta_id'))),

                Forms\Components\Select::make('destino_tampo')
                    ->label('Destino do tampo que sobrou')
                    ->options(fn ($get) => $get('caixa_aberta_id')
                        ? TrocaTampoService::getDestinosTampo((int) $get('caixa_aberta_id'))
                        : [])
                    ->required()
                    ->helperText('O tampo retirado da caixa aberta vai para onde?')
                    ->visible(fn ($get) => filled($get('tampo_usado_id'))),
            ])->columns(2),
        ]);
    }

    public function executar(): void
    {
        $data = $this->form->getState();

        $service = new TrocaTampoService('primary');
        $this->resultado = $service->executarTroca(
            (int) $data['caixa_aberta_id'],
            (int) $data['tampo_usado_id'],
            $data['destino_tampo']
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
        $this->caixa_aberta_id = null;
        $this->tampo_usado_id = null;
        $this->destino_tampo = null;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}

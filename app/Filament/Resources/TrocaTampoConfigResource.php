<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrocaTampoConfigResource\Pages;
use App\Models\TrocaTampoConfig;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TrocaTampoConfigResource extends Resource
{
    protected static ?string $model = TrocaTampoConfig::class;
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Operações';
    protected static ?string $navigationLabel = 'Config. Tampos';
    protected static ?string $modelLabel = 'Configuração de Tampo';
    protected static ?string $pluralModelLabel = 'Configurações de Tampos';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Produto Montado')->schema([
                Forms\Components\TextInput::make('grupo')
                    ->label('Grupo')
                    ->required()
                    ->helperText('Ex: Alana, Evelyn, Fran')
                    ->maxLength(50),
                Forms\Components\TextInput::make('cor')
                    ->label('Cor do Produto')
                    ->required()
                    ->helperText('Ex: Branco, Savana/Preto')
                    ->maxLength(50),
                Forms\Components\Select::make('tipo_tampo')
                    ->label('Tipo de Tampo')
                    ->options([
                        '4bocas' => '4 Bocas (Cooktop)',
                        '5bocas' => '5 Bocas (Cooktop)',
                        'liso' => 'Liso (sem recorte)',
                    ])
                    ->required()
                    ->validationMessages(['unique' => 'Já existe uma configuração para este grupo/cor/tipo de tampo.'])
                    ->unique(
                        table: 'troca_tampos_config',
                        column: 'tipo_tampo',
                        modifyRuleUsing: fn ($rule, $get) => $rule
                            ->where('grupo', $get('grupo'))
                            ->where('cor', $get('cor')),
                        ignoreRecord: true,
                    ),
                Forms\Components\TextInput::make('sku_produto')
                    ->label('SKU do Produto Montado')
                    ->required()
                    ->helperText('SKU no Bling do produto completo (caixa + tampo)'),
                Forms\Components\TextInput::make('nome_produto')
                    ->label('Nome do Produto')
                    ->required()
                    ->helperText('Ex: Alana 4 Bocas Branco'),
            ])->columns(3),

            Forms\Components\Section::make('Tampo')->schema([
                Forms\Components\TextInput::make('sku_tampo')
                    ->label('SKU do Tampo Avulso')
                    ->required()
                    ->helperText('SKU no Bling do tampo separado'),
                Forms\Components\TextInput::make('nome_tampo')
                    ->label('Nome do Tampo')
                    ->required()
                    ->helperText('Ex: Tampo 4 Bocas, Tampo Liso Branco'),
                Forms\Components\TextInput::make('cor_tampo')
                    ->label('Cor do Tampo')
                    ->required()
                    ->helperText('Ex: branco, savana — usado para compatibilidade entre produtos')
                    ->maxLength(50),
                Forms\Components\TextInput::make('familia_tampo')
                    ->label('Família de Tampo')
                    ->required()
                    ->helperText('Grupos que compartilham tampos. Ex: alana, elisa_jade')
                    ->maxLength(50),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('grupo')->label('Grupo')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('cor')->label('Cor'),
                Tables\Columns\TextColumn::make('tipo_tampo')->label('Tipo')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        '4bocas' => 'info',
                        '5bocas' => 'warning',
                        'liso' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('sku_produto')->label('SKU Produto')->searchable(),
                Tables\Columns\TextColumn::make('nome_produto')->label('Produto')->limit(30),
                Tables\Columns\TextColumn::make('sku_tampo')->label('SKU Tampo')->searchable(),
                Tables\Columns\TextColumn::make('nome_tampo')->label('Tampo'),
                Tables\Columns\TextColumn::make('cor_tampo')->label('Cor Tampo'),
                Tables\Columns\TextColumn::make('familia_tampo')->label('Família')->badge(),
            ])
            ->defaultSort('grupo')
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrocaTampoConfigs::route('/'),
            'create' => Pages\CreateTrocaTampoConfig::route('/create'),
            'edit' => Pages\EditTrocaTampoConfig::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}

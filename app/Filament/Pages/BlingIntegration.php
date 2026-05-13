<?php

namespace App\Filament\Pages;

use App\Services\Bling\BlingOAuthService;
use App\Jobs\VariacaoTamposJob;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class BlingIntegration extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cloud-arrow-down';
    protected static ?string $navigationGroup = 'Integrações';
    protected static ?string $navigationLabel = 'Bling';
    protected static ?string $title = 'Integração Bling';
    protected static string $view = 'filament.pages.bling-integration';

    public function getAccounts(): array
    {
        $accounts = [];

        foreach (config('bling.accounts') as $key => $account) {
            $oauth = new BlingOAuthService($key);
            $accounts[$key] = [
                'name' => $account['name'],
                'authorized' => $oauth->isAuthorized(),
                'key' => $key,
            ];
        }

        return $accounts;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('variacao_tampos')
                ->label('Equalizar Variação de Tampos')
                ->icon('heroicon-o-squares-2x2')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Variação de Tampos')
                ->modalDescription('Equaliza o estoque de todas as variações de tampo para o MENOR saldo do grupo. Fluxo correto: 1) Ajuste o estoque do produto principal com o valor correto. 2) Clique neste botão para igualar todos os outros.')
                ->action(function () {
                    VariacaoTamposJob::dispatch('primary');
                    Notification::make()
                        ->title('Equalização enviada para processamento')
                        ->body('Você receberá uma notificação quando concluir.')
                        ->info()
                        ->send();
                }),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}

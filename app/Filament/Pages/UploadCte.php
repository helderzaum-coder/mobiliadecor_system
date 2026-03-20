<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class UploadCte extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-up';
    protected static ?string $navigationGroup = 'Integrações';
    protected static ?string $navigationLabel = 'Upload CT-e';
    protected static ?string $title = 'Upload de CT-e (XML)';
    protected static string $view = 'filament.pages.upload-cte';

    public ?array $data = [];
    public ?array $resultado = null;

    public function mount(): void
    {
        $this->form->fill();
        $this->resultado = null;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\FileUpload::make('arquivos')
                ->label('XMLs de CT-e')
                ->multiple()
                ->required()
                ->directory('cte-uploads')
                ->preserveFilenames()
                ->storeFileNamesIn('nomes_arquivos')
                ->maxSize(51200)
                ->maxFiles(200),
        ])->statePath('data');
    }

    public function processar(): void
    {
        try {
            $data = $this->form->getState();
        } catch (\Exception $e) {
            $this->data = [];
            $this->form->fill();
            Notification::make()->title('Os arquivos expiraram. Faça o upload novamente.')->danger()->send();
            return;
        }

        $arquivos = $data['arquivos'] ?? [];
        if (empty($arquivos)) {
            Notification::make()->title('Selecione pelo menos um arquivo XML.')->danger()->send();
            return;
        }

        $pastaPendentes = storage_path('app/ctes/pendentes');
        $pastaProcessados = storage_path('app/ctes/processados');

        // Garantir que as pastas existem
        File::ensureDirectoryExists($pastaPendentes);
        File::ensureDirectoryExists($pastaProcessados);

        // Coletar chaves de CT-e já existentes (pendentes + processados)
        $chavesExistentes = $this->coletarChavesExistentes($pastaPendentes, $pastaProcessados);

        $resultado = [
            'enviados' => count($arquivos),
            'novos' => 0,
            'duplicados' => 0,
            'invalidos' => 0,
            'detalhes' => [],
        ];

        foreach ($arquivos as $arquivo) {
            $filePath = storage_path('app/public/' . $arquivo);
            if (!file_exists($filePath)) {
                $filePath = storage_path('app/' . $arquivo);
            }

            if (!file_exists($filePath)) {
                $resultado['invalidos']++;
                $resultado['detalhes'][] = basename($arquivo) . ': arquivo não encontrado';
                continue;
            }

            try {
                $xml = simplexml_load_file($filePath);
                if (!$xml) {
                    $resultado['invalidos']++;
                    $resultado['detalhes'][] = basename($arquivo) . ': XML inválido';
                    continue;
                }

                $chaveCte = $this->extrairChaveCte($xml);
                if (!$chaveCte) {
                    $resultado['invalidos']++;
                    $resultado['detalhes'][] = basename($arquivo) . ': não é um CT-e válido (chave não encontrada)';
                    continue;
                }

                // Verificar duplicado
                if (isset($chavesExistentes[$chaveCte])) {
                    $resultado['duplicados']++;
                    continue;
                }

                // Mover para pendentes com nome baseado na chave
                $nomeDestino = $chaveCte . '.xml';
                $destino = $pastaPendentes . '/' . $nomeDestino;
                File::copy($filePath, $destino);

                $chavesExistentes[$chaveCte] = true;
                $resultado['novos']++;
            } catch (\Exception $e) {
                $resultado['invalidos']++;
                $resultado['detalhes'][] = basename($arquivo) . ': ' . $e->getMessage();
                Log::warning("Upload CT-e: erro ao processar", ['arquivo' => $arquivo, 'error' => $e->getMessage()]);
            }
        }

        $this->resultado = $resultado;

        if ($resultado['novos'] > 0) {
            Notification::make()
                ->title("{$resultado['novos']} CT-e(s) enviado(s) com sucesso")
                ->success()->send();
        } else {
            Notification::make()
                ->title('Nenhum CT-e novo encontrado')
                ->warning()->send();
        }

        Log::info("Upload CT-e: concluído", $resultado);

        $this->data = [];
        $this->form->fill();
    }

    private function coletarChavesExistentes(string ...$pastas): array
    {
        $chaves = [];

        foreach ($pastas as $pasta) {
            if (!File::isDirectory($pasta)) continue;

            foreach (File::glob($pasta . '/*.xml') as $arquivo) {
                try {
                    $xml = simplexml_load_file($arquivo);
                    if ($xml) {
                        $chave = $this->extrairChaveCte($xml);
                        if ($chave) {
                            $chaves[$chave] = true;
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return $chaves;
    }

    private function extrairChaveCte(\SimpleXMLElement $xml): ?string
    {
        $namespaces = $xml->getNamespaces(true);
        $ns = reset($namespaces) ?: '';

        if ($ns) {
            $xml->registerXPathNamespace('cte', $ns);
            // Chave de acesso do CT-e
            $chaves = $xml->xpath('//cte:infCte/@Id');
            if (!empty($chaves)) {
                return preg_replace('/^CTe/', '', (string) $chaves[0]);
            }
            $chaves = $xml->xpath('//cte:chCTe');
            if (!empty($chaves)) {
                return (string) $chaves[0];
            }
        }

        // Sem namespace
        $chaves = $xml->xpath('//infCte/@Id');
        if (!empty($chaves)) {
            return preg_replace('/^CTe/', '', (string) $chaves[0]);
        }
        $chaves = $xml->xpath('//chCTe');
        if (!empty($chaves)) {
            return (string) $chaves[0];
        }

        return null;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}

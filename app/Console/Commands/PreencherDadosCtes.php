<?php

namespace App\Console\Commands;

use App\Models\Cte;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PreencherDadosCtes extends Command
{
    protected $signature = 'ctes:preencher-dados';
    protected $description = 'Preenche numero_nfe, dest_documento e rem_documento nos CT-es existentes (via chave + XMLs processados)';

    public function handle(): void
    {
        $count = 0;

        // 1. Preencher numero_nfe a partir da chave_nfe
        $ctes = Cte::where(function ($q) {
            $q->whereNull('numero_nfe')->orWhere('numero_nfe', '');
        })->whereNotNull('chave_nfe')->where('chave_nfe', '!=', '')->get();

        foreach ($ctes as $cte) {
            if (strlen($cte->chave_nfe) === 44) {
                $cte->update(['numero_nfe' => ltrim(substr($cte->chave_nfe, 25, 9), '0')]);
                $count++;
            }
        }

        $this->info("{$count} CT-e(s) com numero_nfe preenchido via chave.");

        // 2. Reprocessar XMLs da pasta processados para extrair documentos
        $pasta = storage_path('app/ctes/processados');
        if (!File::isDirectory($pasta)) {
            $this->warn("Pasta de processados não encontrada: {$pasta}");
            return;
        }

        $arquivos = File::glob($pasta . '/*.xml');
        $atualizados = 0;

        foreach ($arquivos as $arquivo) {
            try {
                $xml = simplexml_load_file($arquivo);
                if (!$xml) continue;

                $namespaces = $xml->getNamespaces(true);
                $ns = reset($namespaces) ?: '';

                if (!$ns) continue;

                $xml->registerXPathNamespace('cte', $ns);

                // Identificar CT-e pela chave
                $chaveCteNodes = $xml->xpath('//cte:infCte/@Id');
                if (empty($chaveCteNodes)) continue;

                $chaveCte = str_replace('CTe', '', (string) $chaveCteNodes[0]);
                $cte = Cte::where('chave_cte', $chaveCte)->first();
                if (!$cte) continue;

                $updates = [];

                // Destinatário CPF/CNPJ
                if (empty($cte->dest_documento)) {
                    $destCnpj = $xml->xpath('//cte:dest/cte:CNPJ');
                    $destCpf = $xml->xpath('//cte:dest/cte:CPF');
                    $doc = !empty($destCnpj) ? (string) $destCnpj[0] : (!empty($destCpf) ? (string) $destCpf[0] : '');
                    if ($doc) $updates['dest_documento'] = $doc;
                }

                // Remetente CPF/CNPJ
                if (empty($cte->rem_documento)) {
                    $remCnpj = $xml->xpath('//cte:rem/cte:CNPJ');
                    $remCpf = $xml->xpath('//cte:rem/cte:CPF');
                    $doc = !empty($remCnpj) ? (string) $remCnpj[0] : (!empty($remCpf) ? (string) $remCpf[0] : '');
                    if ($doc) $updates['rem_documento'] = $doc;
                }

                // numero_nfe se ainda vazio
                if (empty($cte->numero_nfe) && strlen($cte->chave_nfe) === 44) {
                    $updates['numero_nfe'] = ltrim(substr($cte->chave_nfe, 25, 9), '0');
                }

                if (!empty($updates)) {
                    $cte->update($updates);
                    $atualizados++;
                }
            } catch (\Exception $e) {
                $this->warn("Erro em " . basename($arquivo) . ": " . $e->getMessage());
            }
        }

        $this->info("{$atualizados} CT-e(s) atualizados com dados dos XMLs processados.");
    }
}

<?php

namespace App\Services;

use App\Models\Transportadora;
use App\Models\TransportadoraTaxa;
use App\Models\TransportadoraTabelaFrete;
use Illuminate\Support\Facades\Log;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\CSV\Reader as CsvSpoutReader;

class TransportadoraTaxaImportService
{
    /**
     * Lê planilha linha a linha via streaming (sem carregar tudo na memória).
     * Para CSV usa fgetcsv nativo. Para xlsx/ods usa OpenSpout (streaming).
     * Retorna generator de arrays indexados por letra (A, B, C...).
     */
    private static function lerLinhas(string $filePath): \Generator
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $cols = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        if ($ext === 'csv') {
            $handle = fopen($filePath, 'r');
            if (!$handle) return;
            $header = true;
            while (($line = fgetcsv($handle, 0, ';')) !== false) {
                if ($header) { $header = false; continue; }
                // Tentar com vírgula se só veio 1 coluna
                if (count($line) === 1) {
                    $line = str_getcsv($line[0], ',');
                }
                $row = [];
                foreach ($line as $i => $val) {
                    $row[$cols[$i]] = $val;
                }
                yield $row;
            }
            fclose($handle);
            return;
        }

        // xlsx / ods — OpenSpout com leitura streaming (baixo consumo de memória)
        $reader = match ($ext) {
            'ods'  => new OdsReader(),
            default => new XlsxReader(),
        };

        $reader->open($filePath);
        $firstSheet = true;
        foreach ($reader->getSheetIterator() as $sheet) {
            if (!$firstSheet) break;
            $firstSheet = false;
            $rowIndex = 0;
            foreach ($sheet->getRowIterator() as $spoutRow) {
                $rowIndex++;
                if ($rowIndex === 1) continue; // pular cabeçalho
                $cells = $spoutRow->getCells();
                $row = [];
                foreach ($cells as $i => $cell) {
                    if (isset($cols[$i])) {
                        $row[$cols[$i]] = $cell->getValue();
                    }
                }
                yield $row;
            }
        }
        $reader->close();
    }

    /**
     * Importa taxas especiais (TDA, TRT, TAR) de uma planilha.
     * Colunas: tipo_taxa | uf | cidade | cep_inicio | cep_fim | valor_fixo | percentual | observacao
     */
    public static function importarTaxas(string $filePath, int $idTransportadora, bool $limparAntes = false): array
    {
        $resultado = ['importados' => 0, 'erros' => 0, 'mensagens' => []];

        if (!Transportadora::find($idTransportadora)) {
            $resultado['erros']++;
            $resultado['mensagens'][] = 'Transportadora não encontrada.';
            return $resultado;
        }

        if ($limparAntes) {
            TransportadoraTaxa::where('id_transportadora', $idTransportadora)->delete();
        }

        try {
            foreach (self::lerLinhas($filePath) as $row) {
                $tipoTaxa = strtoupper(trim($row['A'] ?? ''));
                if (empty($tipoTaxa)) continue;

                if (!in_array($tipoTaxa, ['TDA', 'TRT', 'TAR', 'TAS', 'OUTROS'])) {
                    $resultado['erros']++;
                    $resultado['mensagens'][] = "Tipo inválido: {$tipoTaxa}";
                    continue;
                }

                try {
                    TransportadoraTaxa::updateOrCreate(
                        [
                            'id_transportadora' => $idTransportadora,
                            'tipo_taxa'   => $tipoTaxa,
                            'uf'          => strtoupper(trim($row['B'] ?? '')) ?: null,
                            'cidade'      => trim($row['C'] ?? '') ?: null,
                            'cep_inicio'  => preg_replace('/\D/', '', $row['D'] ?? '') ?: null,
                            'cep_fim'     => preg_replace('/\D/', '', $row['E'] ?? '') ?: null,
                        ],
                        [
                            'valor_fixo'  => self::parseDecimal($row['F'] ?? null),
                            'percentual'  => self::parseDecimal($row['G'] ?? null),
                            'observacao'  => trim($row['H'] ?? '') ?: null,
                        ]
                    );
                    $resultado['importados']++;
                } catch (\Exception $e) {
                    $resultado['erros']++;
                    Log::warning("Erro importando taxa", ['error' => $e->getMessage()]);
                }
            }
        } catch (\Exception $e) {
            $resultado['erros']++;
            $resultado['mensagens'][] = 'Erro ao ler arquivo: ' . $e->getMessage();
        }

        return $resultado;
    }

    /**
     * Importa tabela de frete de uma planilha.
     * Colunas: uf | cep_inicio | cep_fim | regiao | peso_min | peso_max | valor_kg* | valor_fixo* | frete_minimo* | despacho* | pedagio_valor* | pedagio_fracao_kg* | adv_%* | adv_minimo* | gris_%* | gris_minimo*
     */
    public static function importarTabelaFrete(string $filePath, int $idTransportadora, bool $limparAntes = false): array
    {
        $resultado = ['importados' => 0, 'erros' => 0, 'mensagens' => []];

        if (!Transportadora::find($idTransportadora)) {
            $resultado['erros']++;
            $resultado['mensagens'][] = 'Transportadora não encontrada.';
            return $resultado;
        }

        // Primeira passada: identificar UFs presentes na planilha
        $ufsNaPlanilha = [];
        foreach (self::lerLinhas($filePath) as $row) {
            $uf = strtoupper(trim($row['A'] ?? ''));
            if (!empty($uf)) {
                $ufsNaPlanilha[$uf] = true;
            }
        }

        // Limpar apenas as UFs que estão na planilha (não afeta outros estados)
        if (!empty($ufsNaPlanilha)) {
            $deletados = TransportadoraTabelaFrete::where('id_transportadora', $idTransportadora)
                ->whereIn('uf', array_keys($ufsNaPlanilha))
                ->delete();
            if ($deletados > 0) {
                $resultado['mensagens'][] = "{$deletados} registro(s) antigos removidos para UF(s): " . implode(', ', array_keys($ufsNaPlanilha));
            }
        }

        $ufsEncontradas = [];
        $batch = [];
        $now = now();

        try {
            foreach (self::lerLinhas($filePath) as $row) {
                $uf = strtoupper(trim($row['A'] ?? ''));
                if (empty($uf)) continue;

                $ufsEncontradas[$uf] = true;

                $cepA = preg_replace('/\D/', '', $row['B'] ?? '') ?: null;
                $cepB = preg_replace('/\D/', '', $row['C'] ?? '') ?: null;
                if ($cepA && $cepB && $cepA > $cepB) {
                    [$cepA, $cepB] = [$cepB, $cepA];
                }

                $pesoMin = self::parseDecimal($row['E'] ?? 0) ?? 0;
                $pesoMax = self::parseDecimal($row['F'] ?? 0) ?? 0;

                $batch[] = [
                    'id_transportadora' => $idTransportadora,
                    'uf'                => $uf,
                    'cep_inicio'        => $cepA,
                    'cep_fim'           => $cepB,
                    'regiao'            => trim($row['D'] ?? '') ?: null,
                    'peso_min'          => $pesoMin,
                    'peso_max'          => $pesoMax,
                    'valor_kg'          => self::parseDecimal($row['G'] ?? null),
                    'valor_fixo'        => self::parseDecimal($row['H'] ?? null),
                    'frete_minimo'      => self::parseDecimal($row['I'] ?? null) ?? 0,
                    'despacho'          => self::parseDecimal($row['J'] ?? null),
                    'pedagio_valor'     => self::parseDecimal($row['K'] ?? null),
                    'pedagio_fracao_kg' => self::parseDecimal($row['L'] ?? null),
                    'adv_percentual'    => self::parseDecimal($row['M'] ?? null),
                    'adv_minimo'        => self::parseDecimal($row['N'] ?? null),
                    'gris_percentual'   => self::parseDecimal($row['O'] ?? null),
                    'gris_minimo'       => self::parseDecimal($row['P'] ?? null),
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];

                if (count($batch) >= 500) {
                    try {
                        TransportadoraTabelaFrete::upsert($batch, [
                            'id_transportadora', 'uf', 'cep_inicio', 'cep_fim', 'peso_min', 'peso_max',
                        ], [
                            'regiao', 'valor_kg', 'valor_fixo', 'frete_minimo', 'despacho',
                            'pedagio_valor', 'pedagio_fracao_kg', 'adv_percentual', 'adv_minimo',
                            'gris_percentual', 'gris_minimo', 'updated_at',
                        ]);
                        $resultado['importados'] += count($batch);
                    } catch (\Exception $e) {
                        $resultado['erros'] += count($batch);
                        Log::warning("Erro batch frete", ['error' => $e->getMessage()]);
                    }
                    $batch = [];
                }
            }
        } catch (\Exception $e) {
            $resultado['erros']++;
            $resultado['mensagens'][] = 'Erro ao ler arquivo: ' . $e->getMessage();
        }

        if (!empty($batch)) {
            try {
                TransportadoraTabelaFrete::upsert($batch, [
                    'id_transportadora', 'uf', 'cep_inicio', 'cep_fim', 'peso_min', 'peso_max',
                ], [
                    'regiao', 'valor_kg', 'valor_fixo', 'frete_minimo', 'despacho',
                    'pedagio_valor', 'pedagio_fracao_kg', 'adv_percentual', 'adv_minimo',
                    'gris_percentual', 'gris_minimo', 'updated_at',
                ]);
                $resultado['importados'] += count($batch);
            } catch (\Exception $e) {
                $resultado['erros'] += count($batch);
                Log::warning("Erro batch frete final", ['error' => $e->getMessage()]);
            }
        }

        // Auto-cadastrar UFs encontradas
        $ufsExistentes = \App\Models\TransportadoraUf::where('id_transportadora', $idTransportadora)
            ->pluck('uf')->flip()->toArray();
        $ufsAdicionadas = 0;
        foreach (array_keys($ufsEncontradas) as $uf) {
            if (!isset($ufsExistentes[$uf])) {
                \App\Models\TransportadoraUf::create(['id_transportadora' => $idTransportadora, 'uf' => $uf]);
                $ufsAdicionadas++;
            }
        }
        if ($ufsAdicionadas > 0) {
            $resultado['mensagens'][] = "{$ufsAdicionadas} UF(s) adicionadas automaticamente";
        }

        return $resultado;
    }

    public static function parseDecimal($value): ?float
    {
        if ($value === null || $value === '') return null;

        if (is_numeric($value) && !is_string($value)) {
            return (float) $value;
        }

        $str = trim((string) $value);
        if ($str === '') return null;

        if (str_contains($str, ',')) {
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
        }

        $str = preg_replace('/[^\d.\-]/', '', $str);
        return is_numeric($str) ? (float) $str : null;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoteRecebimento extends Model
{
    protected $table = 'lotes_recebimento';

    public static function gerarDescricao(?string $bancoNome, ?string $dataRecebimento, ?string $descricaoManual = null): string
    {
        if (!empty(trim((string) $descricaoManual))) {
            return trim((string) $descricaoManual);
        }

        $banco = trim((string) ($bancoNome ?? ''));
        $data = trim((string) ($dataRecebimento ?? ''));

        if ($banco !== '' && $data !== '') {
            $dataFormatada = \Carbon\Carbon::parse($data)->format('d/m/Y');
            return "{$banco} - {$dataFormatada}";
        }

        if ($banco !== '') {
            return $banco;
        }

        if ($data !== '') {
            return \Carbon\Carbon::parse($data)->format('d/m/Y');
        }

        return 'Lote';
    }

    protected $fillable = [
        'data_recebimento',
        'descricao',
        'valor_total',
        'quantidade_contas',
    ];

    protected $casts = [
        'data_recebimento' => 'date',
        'valor_total' => 'decimal:2',
    ];

    public function contasReceber(): HasMany
    {
        return $this->hasMany(ContaReceber::class, 'lote_recebimento_id');
    }

    public function descontos(): HasMany
    {
        return $this->hasMany(ContaPagar::class, 'lote_recebimento_id');
    }
}

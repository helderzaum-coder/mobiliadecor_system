<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaixaTravamento extends Model
{
    protected $table = 'caixa_travamentos';

    protected $fillable = [
        'conta_bancaria_id',
        'data_travamento',
        'observacao',
        'criado_por',
    ];

    protected $casts = [
        'data_travamento' => 'date',
    ];

    public function contaBancaria()
    {
        return $this->belongsTo(ContaBancaria::class, 'conta_bancaria_id');
    }

    /**
     * Verifica se uma data está travada para uma conta bancária.
     * Trava global (conta_bancaria_id = null) também bloqueia.
     */
    public static function estaTravado(?int $contaBancariaId, string $data): bool
    {
        return self::where('data_travamento', '>=', $data)
            ->where(function ($q) use ($contaBancariaId) {
                $q->whereNull('conta_bancaria_id');
                if ($contaBancariaId) {
                    $q->orWhere('conta_bancaria_id', $contaBancariaId);
                }
            })
            ->exists();
    }

    /**
     * Retorna a data de travamento mais recente para uma conta (ou global).
     */
    public static function dataTravamento(?int $contaBancariaId): ?string
    {
        $travamento = self::where(function ($q) use ($contaBancariaId) {
                $q->whereNull('conta_bancaria_id');
                if ($contaBancariaId) {
                    $q->orWhere('conta_bancaria_id', $contaBancariaId);
                }
            })
            ->orderByDesc('data_travamento')
            ->first();

        return $travamento?->data_travamento?->format('Y-m-d');
    }
}

<?php

namespace App\Models\Contable;

use App\Models\Caja\InterbankingMovimiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConciliacionBancariaExtractoLinea extends Model
{
    protected $table = 'conciliacion_bancaria_extracto_linea';

    protected $fillable = [
        'extracto_id',
        'orden',
        'fecha',
        'dh',
        'importe',
        'concepto',
        'comprobante',
        'referencia',
        'hash_banco',
        'interbanking_movimiento_id',
        'raw_json',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'importe' => 'decimal:2',
            'raw_json' => 'array',
        ];
    }

    public function extracto(): BelongsTo
    {
        return $this->belongsTo(ConciliacionBancariaExtracto::class, 'extracto_id');
    }

    public function interbankingMovimiento(): BelongsTo
    {
        return $this->belongsTo(InterbankingMovimiento::class, 'interbanking_movimiento_id');
    }
}

<?php

namespace App\Models\Contable;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class BienUsoAmortizacion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'bien_uso_amortizacion';

    protected $fillable = [
        'bien_uso_id',
        'empresa_id',
        'periodo_anio',
        'monto_ejercicio',
        'monto_anterior',
        'monto_acumulado',
        'vida_util_transcurrida',
        'monto_mensual',
    ];

    protected function casts(): array
    {
        return [
            'periodo_anio' => 'integer',
            'monto_ejercicio' => 'decimal:2',
            'monto_anterior' => 'decimal:2',
            'monto_acumulado' => 'decimal:2',
            'vida_util_transcurrida' => 'integer',
            'monto_mensual' => 'decimal:2',
        ];
    }

    public function bienUso(): BelongsTo
    {
        return $this->belongsTo(BienUso::class, 'bien_uso_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}

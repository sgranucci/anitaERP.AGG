<?php

namespace App\Models\Sueldos;

use App\Support\Sueldos\SiradigTablas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Concepto de una presentación F572: deducción (D), retención/percepción/pago (R)
 * o ajuste (J). Espejo de ConceptoType / AjusteType.
 */
class Siradig_Concepto_Sueldos extends Model
{
    protected $table = 'siradig_concepto_sueldos';

    protected $fillable = [
        'presentacion_id',
        'grupo',
        'tipo',
        'tipo_doc',
        'nro_doc',
        'cuit',
        'denominacion',
        'desc_basica',
        'desc_adicional',
        'monto_total',
    ];

    protected $casts = [
        'presentacion_id' => 'integer',
        'tipo' => 'integer',
        'tipo_doc' => 'integer',
        'monto_total' => 'decimal:2',
    ];

    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(Siradig_Presentacion_Sueldos::class, 'presentacion_id');
    }

    public function periodos(): HasMany
    {
        return $this->hasMany(Siradig_Concepto_Periodo_Sueldos::class, 'concepto_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(Siradig_Concepto_Detalle_Sueldos::class, 'concepto_id');
    }

    public function getTipoDescripcionAttribute(): string
    {
        return SiradigTablas::concepto((string) $this->grupo, $this->tipo);
    }
}

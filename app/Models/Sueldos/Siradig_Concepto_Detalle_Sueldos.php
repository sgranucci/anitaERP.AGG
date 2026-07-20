<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Detalle nombre/valor de un concepto F572 (DetalleType).
 */
class Siradig_Concepto_Detalle_Sueldos extends Model
{
    protected $table = 'siradig_concepto_detalle_sueldos';

    protected $fillable = [
        'concepto_id',
        'nombre',
        'valor',
    ];

    protected $casts = [
        'concepto_id' => 'integer',
    ];

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(Siradig_Concepto_Sueldos::class, 'concepto_id');
    }
}

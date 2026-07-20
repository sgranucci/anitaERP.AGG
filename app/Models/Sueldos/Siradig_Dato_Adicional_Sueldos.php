<?php

namespace App\Models\Sueldos;

use App\Support\Sueldos\SiradigTablas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dato adicional de una presentación F572 (DatoAdicionalType, Tabla 11).
 */
class Siradig_Dato_Adicional_Sueldos extends Model
{
    protected $table = 'siradig_dato_adicional_sueldos';

    protected $fillable = [
        'presentacion_id',
        'nombre',
        'mes_desde',
        'mes_hasta',
        'valor',
    ];

    protected $casts = [
        'presentacion_id' => 'integer',
        'mes_desde' => 'integer',
        'mes_hasta' => 'integer',
    ];

    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(Siradig_Presentacion_Sueldos::class, 'presentacion_id');
    }

    public function getNombreDescripcionAttribute(): string
    {
        return SiradigTablas::datoAdicional($this->nombre);
    }
}

<?php

namespace App\Models\Sueldos;

use App\Support\Sueldos\SiradigTablas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Carga de familia declarada en una presentación F572 (SiRADIG).
 */
class Siradig_Carga_Familia_Sueldos extends Model
{
    protected $table = 'siradig_carga_familia_sueldos';

    protected $fillable = [
        'presentacion_id',
        'tipo_doc',
        'nro_doc',
        'apellido',
        'nombre',
        'fecha_nac',
        'mes_desde',
        'mes_hasta',
        'parentesco',
        'vigente_proximos_periodos',
        'fecha_limite',
        'porcentaje_deduccion',
    ];

    protected $casts = [
        'presentacion_id' => 'integer',
        'tipo_doc' => 'integer',
        'mes_desde' => 'integer',
        'mes_hasta' => 'integer',
        'parentesco' => 'integer',
        'porcentaje_deduccion' => 'integer',
        'fecha_nac' => 'date',
        'fecha_limite' => 'date',
    ];

    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(Siradig_Presentacion_Sueldos::class, 'presentacion_id');
    }

    public function getParentescoDescripcionAttribute(): string
    {
        return SiradigTablas::parentesco($this->parentesco);
    }
}

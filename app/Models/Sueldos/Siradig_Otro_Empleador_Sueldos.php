<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Otro empleador / entidad (pluriempleo) informado en una presentación F572.
 */
class Siradig_Otro_Empleador_Sueldos extends Model
{
    protected $table = 'siradig_otro_empleador_sueldos';

    protected $fillable = [
        'presentacion_id',
        'cuit',
        'denominacion',
        'convenio_colectivo',
        'transporte_larga_dist',
        'transporte_terr_larga_dist',
    ];

    protected $casts = [
        'presentacion_id' => 'integer',
    ];

    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(Siradig_Presentacion_Sueldos::class, 'presentacion_id');
    }

    public function meses(): HasMany
    {
        return $this->hasMany(Siradig_Otro_Empleador_Mes_Sueldos::class, 'otro_empleador_id')->orderBy('mes');
    }
}

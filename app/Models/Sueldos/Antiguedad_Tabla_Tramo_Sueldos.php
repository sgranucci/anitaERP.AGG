<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tramo de antigüedad (Anita antmov: antv_anio / antv_porcentaje / antv_cantidad).
 */
class Antiguedad_Tabla_Tramo_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'antiguedad_tabla_tramo_sueldos';

    protected $fillable = [
        'antiguedad_tabla_id',
        'anio',
        'porcentaje',
        'cantidad',
        'nro_linea',
    ];

    protected $casts = [
        'antiguedad_tabla_id' => 'integer',
        'anio' => 'integer',
        'porcentaje' => 'decimal:6',
        'cantidad' => 'decimal:6',
        'nro_linea' => 'integer',
    ];

    public function tabla()
    {
        return $this->belongsTo(Antiguedad_Tabla_Sueldos::class, 'antiguedad_tabla_id');
    }
}

<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;

/**
 * Bitácora de importación de nómina confidencial (auxconf/auxconfh).
 */
class Liquidacion_Importacion_Sueldos extends Model
{
    protected $table = 'liquidacion_importacion_sueldos';

    protected $fillable = [
        'liquidacion_id',
        'usuario_id',
        'fuente',
        'plan_hash',
        'empresa_anita',
        'liquidacion_anita',
        'filas',
        'recibos_creados',
        'recibos_actualizados',
        'recibos_iguales',
        'empleados_marcados',
        'resumen',
    ];

    protected $casts = [
        'liquidacion_id' => 'integer',
        'usuario_id' => 'integer',
        'empresa_anita' => 'integer',
        'liquidacion_anita' => 'integer',
        'filas' => 'integer',
        'recibos_creados' => 'integer',
        'recibos_actualizados' => 'integer',
        'recibos_iguales' => 'integer',
        'empleados_marcados' => 'integer',
        'resumen' => 'array',
    ];

    public function liquidacion()
    {
        return $this->belongsTo(Liquidacion_Sueldos::class, 'liquidacion_id');
    }
}

<?php

namespace App\Models\Sueldos;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Cabecera de tabla de antigüedad (Anita antmov / ANT(n)).
 * Código 1–15; los tramos viven en antiguedad_tabla_tramo_sueldos.
 */
class Antiguedad_Tabla_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'antiguedad_tabla_sueldos';

    protected $fillable = [
        'empresa_id',
        'codigo',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'codigo' => 'integer',
        'activo' => 'boolean',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function tramos()
    {
        return $this->hasMany(Antiguedad_Tabla_Tramo_Sueldos::class, 'antiguedad_tabla_id')
            ->orderBy('anio')
            ->orderBy('nro_linea');
    }
}

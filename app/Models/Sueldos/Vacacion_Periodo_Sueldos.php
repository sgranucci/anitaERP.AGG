<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Vacacion_Periodo_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'vacacion_periodo_sueldos';

    protected $fillable = [
        'vacacion_id',
        'nro_linea',
        'fecha_desde',
        'fecha_hasta',
        'tipo_dia',
        'cantidad_dias',
    ];

    protected $casts = [
        'vacacion_id' => 'integer',
        'nro_linea' => 'integer',
        'fecha_desde' => 'date',
        'fecha_hasta' => 'date',
        'cantidad_dias' => 'integer',
    ];

    public function vacacion()
    {
        return $this->belongsTo(Vacacion_Sueldos::class, 'vacacion_id');
    }
}

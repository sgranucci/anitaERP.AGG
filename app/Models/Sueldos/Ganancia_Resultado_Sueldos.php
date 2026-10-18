<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Ganancia_Resultado_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'ganancia_resultado_sueldos';

    protected $fillable = [
        'empresa_id', 'empleado_id', 'anio', 'mes', 'linea_codigo',
        'valor', 'cantidad', 'liquidacion_id',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'empleado_id' => 'integer',
        'anio' => 'integer',
        'mes' => 'integer',
        'valor' => 'decimal:2',
        'cantidad' => 'decimal:4',
        'liquidacion_id' => 'integer',
    ];
}

<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Lsd_Recibo_Base_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'lsd_recibo_base_sueldos';

    protected $fillable = [
        'recibo_id', 'liquidacion_id', 'empleado_id',
        'dias_tope', 'dias_trabajados', 'horas_trabajadas',
        'rem_bruta',
        'base_1', 'base_2', 'base_3', 'base_4', 'base_5',
        'base_6', 'base_7', 'base_8', 'base_9', 'base_10',
        'importe_detraer',
        'situacion_1', 'dia_inicio_1',
        'situacion_2', 'dia_inicio_2',
        'situacion_3', 'dia_inicio_3',
    ];

    protected $casts = [
        'recibo_id' => 'integer',
        'liquidacion_id' => 'integer',
        'empleado_id' => 'integer',
        'dias_tope' => 'integer',
        'dias_trabajados' => 'integer',
        'horas_trabajadas' => 'integer',
        'rem_bruta' => 'decimal:2',
        'base_1' => 'decimal:2',
        'base_2' => 'decimal:2',
        'base_3' => 'decimal:2',
        'base_4' => 'decimal:2',
        'base_5' => 'decimal:2',
        'base_6' => 'decimal:2',
        'base_7' => 'decimal:2',
        'base_8' => 'decimal:2',
        'base_9' => 'decimal:2',
        'base_10' => 'decimal:2',
        'importe_detraer' => 'decimal:2',
        'dia_inicio_1' => 'integer',
        'dia_inicio_2' => 'integer',
        'dia_inicio_3' => 'integer',
    ];

    public function recibo()
    {
        return $this->belongsTo(Liquidacion_Recibo_Sueldos::class, 'recibo_id');
    }
}

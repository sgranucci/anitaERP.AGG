<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Empleado_Cuota_Movimiento_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'empleado_cuota_movimiento_sueldos';

    protected $fillable = [
        'empleado_id',
        'anio_periodo',
        'origen',
        'fecha',
        'dias',
        'ausencia_id',
        'descripcion',
        'usuario_id',
    ];

    protected $casts = [
        'empleado_id' => 'integer',
        'anio_periodo' => 'integer',
        'fecha' => 'date',
        'dias' => 'decimal:2',
        'ausencia_id' => 'integer',
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado_Sueldos::class, 'empleado_id');
    }

    public function ausencia()
    {
        return $this->belongsTo(Empleado_Ausencia_Sueldos::class, 'ausencia_id');
    }
}

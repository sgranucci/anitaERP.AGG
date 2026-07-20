<?php

namespace App\Models\Sueldos;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Empleado_Ingreso_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'empleado_ingreso_sueldos';

    protected $fillable = [
        'empleado_id',
        'fecha_ingreso',
        'fecha_egreso',
        'motivoegreso_id',
        'comentario_baja',
        'tipo_movimiento',
        'usuario_id',
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'fecha_egreso' => 'date',
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado_Sueldos::class, 'empleado_id');
    }

    public function motivoegreso()
    {
        return $this->belongsTo(Motivoegreso_Sueldos::class, 'motivoegreso_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

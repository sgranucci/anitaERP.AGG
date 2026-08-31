<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Lsd_Empleado_Revista_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'lsd_empleado_revista_sueldos';

    protected $fillable = [
        'empleado_id', 'periodo', 'nro', 'situacion', 'dia_inicio',
    ];

    protected $casts = [
        'empleado_id' => 'integer',
        'periodo' => 'integer',
        'nro' => 'integer',
        'dia_inicio' => 'integer',
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado_Sueldos::class, 'empleado_id');
    }
}

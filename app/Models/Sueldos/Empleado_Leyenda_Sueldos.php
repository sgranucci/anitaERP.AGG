<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Empleado_Leyenda_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'empleado_leyenda_sueldos';

    protected $fillable = [
        'empleado_id',
        'linea',
        'leyenda',
    ];

    protected $casts = [
        'empleado_id' => 'integer',
        'linea' => 'integer',
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado_Sueldos::class, 'empleado_id');
    }
}

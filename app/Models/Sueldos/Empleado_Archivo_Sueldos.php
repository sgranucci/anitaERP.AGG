<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Empleado_Archivo_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'empleado_archivo_sueldos';

    protected $fillable = [
        'empleado_id',
        'nombrearchivo',
    ];

    protected $casts = [
        'empleado_id' => 'integer',
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado_Sueldos::class, 'empleado_id');
    }
}

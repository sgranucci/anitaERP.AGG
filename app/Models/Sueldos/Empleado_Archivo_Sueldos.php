<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;

class Empleado_Archivo_Sueldos extends Model
{
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

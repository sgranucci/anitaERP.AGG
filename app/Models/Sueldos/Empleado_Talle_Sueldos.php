<?php

namespace App\Models\Sueldos;

use App\Models\Stock\Talle;
use Illuminate\Database\Eloquent\Model;

class Empleado_Talle_Sueldos extends Model
{
    protected $table = 'empleado_talle_sueldos';

    protected $fillable = [
        'empleado_id',
        'prenda_id',
        'talle_id',
    ];

    protected $casts = [
        'empleado_id' => 'integer',
        'prenda_id' => 'integer',
        'talle_id' => 'integer',
    ];

    public function prenda()
    {
        return $this->belongsTo(Prenda_Sueldos::class, 'prenda_id');
    }

    public function talle()
    {
        return $this->belongsTo(Talle::class, 'talle_id');
    }
}

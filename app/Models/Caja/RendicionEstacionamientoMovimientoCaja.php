<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;

class RendicionEstacionamientoMovimientoCaja extends Model
{
    protected $table = 'rendicion_estacionamiento_movimiento_caja';

    protected $fillable = [
        'rendicion_estacionamiento_caja_id',
        'cuentacaja_id',
        'monto',
        'cotizacion',
    ];

    protected $casts = [
        'monto' => 'float',
        'cotizacion' => 'float',
    ];

    public function rendicion()
    {
        return $this->belongsTo(RendicionEstacionamientoCaja::class, 'rendicion_estacionamiento_caja_id');
    }

    public function cuentacaja()
    {
        return $this->belongsTo(Cuentacaja::class, 'cuentacaja_id');
    }
}

<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;

class RendicionGastronomiaMovimientoCaja extends Model
{
    protected $table = 'rendicion_gastronomia_movimiento_caja';

    protected $fillable = [
        'rendicion_gastronomia_caja_id',
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
        return $this->belongsTo(RendicionGastronomiaCaja::class, 'rendicion_gastronomia_caja_id');
    }

    public function cuentacaja()
    {
        return $this->belongsTo(Cuentacaja::class, 'cuentacaja_id');
    }
}

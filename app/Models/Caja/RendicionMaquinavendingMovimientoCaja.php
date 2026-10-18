<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class RendicionMaquinavendingMovimientoCaja extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'rendicion_maquinavending_movimiento_caja';

    protected $fillable = [
        'rendicion_maquinavending_caja_id',
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
        return $this->belongsTo(RendicionMaquinavendingCaja::class, 'rendicion_maquinavending_caja_id');
    }

    public function cuentacaja()
    {
        return $this->belongsTo(Cuentacaja::class, 'cuentacaja_id');
    }
}

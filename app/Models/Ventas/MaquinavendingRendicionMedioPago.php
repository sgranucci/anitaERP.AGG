<?php

namespace App\Models\Ventas;

use App\Models\Caja\Cuentacaja;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class MaquinavendingRendicionMedioPago extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'maquinavending_rendicion_id',
        'cuentacaja_id',
        'monto',
        'cotizacion',
    ];

    protected $casts = [
        'monto' => 'float',
        'cotizacion' => 'float',
    ];

    protected $table = 'maquinavending_rendicion_medio_pago';

    public function rendicion()
    {
        return $this->belongsTo(MaquinavendingRendicion::class, 'maquinavending_rendicion_id');
    }

    public function cuentacaja()
    {
        return $this->belongsTo(Cuentacaja::class, 'cuentacaja_id');
    }
}

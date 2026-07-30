<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class RendicionMaquinaValor extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'rendicion_maquina_valor';

    protected $fillable = [
        'rendicion_maquina_id',
        'cuentacaja_id',
        'codigo_valormae',
        'monto',
        'cotizacion',
        'orden',
    ];

    protected $casts = [
        'codigo_valormae' => 'integer',
        'monto' => 'decimal:2',
        'cotizacion' => 'decimal:6',
        'orden' => 'integer',
    ];

    public function rendicionMaquina()
    {
        return $this->belongsTo(RendicionMaquina::class, 'rendicion_maquina_id');
    }

    public function cuentacaja()
    {
        return $this->belongsTo(Cuentacaja::class, 'cuentacaja_id');
    }
}

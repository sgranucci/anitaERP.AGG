<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class RendicionMaquinaGasto extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'rendicion_maquina_gasto';

    protected $fillable = [
        'rendicion_maquina_id',
        'apertura_gasto_id',
        'monto',
        'orden',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'orden' => 'integer',
    ];

    public function rendicionMaquina()
    {
        return $this->belongsTo(RendicionMaquina::class, 'rendicion_maquina_id');
    }

    public function aperturaGasto()
    {
        return $this->belongsTo(AperturaGasto::class, 'apertura_gasto_id');
    }
}

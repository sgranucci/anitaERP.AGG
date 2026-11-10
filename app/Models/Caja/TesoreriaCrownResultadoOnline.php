<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Sala;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownResultadoOnline extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_resultado_online';

    protected $fillable = [
        'empresa_id',
        'sala_id',
        'fecha',
        'attendance',
        'apostado',
        'pagado',
        'drop',
        'resultado',
        'porcentaje',
        'poder_publico',
        'betsur_attendance',
        'betsur_apostado',
        'betsur_pagado',
        'betsur_drop',
        'betsur_resultado',
        'betsur_porcentaje',
        'betsur_poder_publico',
        'coin_in',
        'coin_out',
        'pago_manual',
        'cantidad_slots',
        'slot_resultado',
        'subtotal_slot',
        'usuario_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'attendance' => 'integer',
        'apostado' => 'decimal:2',
        'pagado' => 'decimal:2',
        'drop' => 'decimal:2',
        'resultado' => 'decimal:2',
        'porcentaje' => 'decimal:4',
        'poder_publico' => 'decimal:2',
        'betsur_attendance' => 'integer',
        'betsur_apostado' => 'decimal:2',
        'betsur_pagado' => 'decimal:2',
        'betsur_drop' => 'decimal:2',
        'betsur_resultado' => 'decimal:2',
        'betsur_porcentaje' => 'decimal:4',
        'betsur_poder_publico' => 'decimal:2',
        'coin_in' => 'decimal:2',
        'coin_out' => 'decimal:2',
        'pago_manual' => 'decimal:2',
        'cantidad_slots' => 'integer',
        'slot_resultado' => 'decimal:2',
        'subtotal_slot' => 'decimal:2',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public static function deSalaFecha(int $salaId, string $fecha): ?self
    {
        if ($salaId <= 0 || $fecha === '') {
            return null;
        }

        return self::query()
            ->where('sala_id', $salaId)
            ->whereDate('fecha', $fecha)
            ->first();
    }
}

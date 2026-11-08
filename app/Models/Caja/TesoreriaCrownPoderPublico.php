<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Sala;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownPoderPublico extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ORIGEN_ERP = 'erp';

    public const ORIGEN_ANITA = 'anita';

    public const ORIGEN_MIXTO = 'mixto';

    protected $table = 'tesoreria_crown_poder_publico';

    protected $fillable = [
        'sala_id',
        'fecha',
        'ficha_dia',
        'ficha_mes',
        'ficha_acum',
        'slot_dia',
        'slot_mes',
        'slot_acum',
        'voucher_mesa',
        'slot_resultado',
        'subtotal_slot',
        'origen',
        'detalle',
        'generado_at',
    ];

    protected $casts = [
        'fecha' => 'date',
        'ficha_dia' => 'decimal:2',
        'ficha_mes' => 'decimal:2',
        'ficha_acum' => 'decimal:2',
        'slot_dia' => 'decimal:2',
        'slot_mes' => 'decimal:2',
        'slot_acum' => 'decimal:2',
        'voucher_mesa' => 'decimal:2',
        'slot_resultado' => 'decimal:2',
        'subtotal_slot' => 'decimal:2',
        'detalle' => 'array',
        'generado_at' => 'datetime',
    ];

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public static function deSalaFecha(int $salaId, string $fechaYmd): ?self
    {
        if ($salaId <= 0 || $fechaYmd === '') {
            return null;
        }

        return self::query()
            ->where('sala_id', $salaId)
            ->whereDate('fecha', $fechaYmd)
            ->first();
    }
}

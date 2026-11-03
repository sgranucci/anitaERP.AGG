<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Moneda;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Reemplazo de un cheque anulado por transferencia bancaria (sin cheque nuevo).
 */
class ChequeReemplazoTransferencia extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'cheque_reemplazo_transferencia';

    protected $fillable = [
        'caja_movimiento_id',
        'cheque_id',
        'cuentacaja_id',
        'fecha',
        'monto',
        'moneda_id',
        'cotizacion',
    ];

    public function cajaMovimiento()
    {
        return $this->belongsTo(Caja_Movimiento::class, 'caja_movimiento_id');
    }

    public function chequeAnulado()
    {
        return $this->belongsTo(Cheque::class, 'cheque_id');
    }

    public function cuentacajas()
    {
        return $this->belongsTo(Cuentacaja::class, 'cuentacaja_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    /**
     * Si este movimiento era el único reemplazo, el cheque vuelve a estar vigente.
     */
    public static function restaurarChequeSiQuedoSinReemplazo(int $chequeId, int $exceptoMovimientoId): void
    {
        if ($chequeId <= 0) {
            return;
        }

        $tieneChequeNuevo = Cheque::query()
            ->where('cheque_reemplaza_id', $chequeId)
            ->exists();
        $tieneOtraTransferencia = static::query()
            ->where('cheque_id', $chequeId)
            ->where('caja_movimiento_id', '!=', $exceptoMovimientoId)
            ->exists();
        if ($tieneChequeNuevo || $tieneOtraTransferencia) {
            return;
        }

        $cheque = Cheque::query()->find($chequeId);
        if ($cheque === null) {
            return;
        }
        if (strtoupper(trim((string) $cheque->estado)) !== 'A') {
            return;
        }

        $cheque->estado = ' ';
        $cheque->save();
    }

    public static function restaurarChequesDelMovimiento(int $cajaMovimientoId): void
    {
        if ($cajaMovimientoId <= 0) {
            return;
        }

        $ids = static::query()
            ->where('caja_movimiento_id', $cajaMovimientoId)
            ->pluck('cheque_id');

        foreach ($ids as $chequeId) {
            static::restaurarChequeSiQuedoSinReemplazo((int) $chequeId, $cajaMovimientoId);
        }
    }
}

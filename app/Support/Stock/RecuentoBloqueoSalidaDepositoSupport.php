<?php

namespace App\Support\Stock;

use App\Models\Stock\Depmae;
use App\Models\Stock\Recuento;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Bloquea salidas de stock (mov. manual / TM origen) si hay un recuento
 * PENDIENTE o SUSPENDIDO en el depósito con fecha ≤ fecha del movimiento.
 */
final class RecuentoBloqueoSalidaDepositoSupport
{
    /**
     * @return list<string>
     */
    public static function estadosAbiertos(): array
    {
        return [
            Recuento::ESTADO_PENDIENTE,
            Recuento::ESTADO_SUSPENDIDO,
        ];
    }

    public static function assertSalidaPermitida(
        int $depositoId,
        ?string $fechaMovimiento,
        ?string $signoCantidad,
    ): void {
        if ($depositoId <= 0) {
            return;
        }
        if (! MovimientoStockSalidaSaldoSupport::esSignoRestaStock($signoCantidad)) {
            return;
        }

        $fecha = self::normalizarFecha($fechaMovimiento);
        $recuento = self::recuentoAbiertoQueBloquea($depositoId, $fecha);
        if (! $recuento) {
            return;
        }

        throw new InvalidArgumentException(self::mensajeBloqueo($recuento, $depositoId, $fecha));
    }

    public static function recuentoAbiertoQueBloquea(int $depositoId, string $fechaMovimiento): ?Recuento
    {
        if ($depositoId <= 0) {
            return null;
        }

        return Recuento::query()
            ->where('deposito_id', $depositoId)
            ->whereIn('estado', self::estadosAbiertos())
            ->whereDate('fecha', '<=', $fechaMovimiento)
            ->orderBy('fecha')
            ->orderBy('id')
            ->first(['id', 'codigo', 'fecha', 'estado', 'deposito_id']);
    }

    public static function mensajeBloqueo(Recuento $recuento, int $depositoId, string $fechaMovimiento): string
    {
        $deposito = Depmae::query()->find($depositoId);
        $depLabel = $deposito
            ? trim((string) $deposito->codigo.' '.(string) $deposito->nombre)
            : ('#'.$depositoId);
        $fechaRc = optional($recuento->fecha)->format('d/m/Y') ?: '—';
        $fechaMovFmt = Carbon::parse($fechaMovimiento)->format('d/m/Y');
        $estado = Recuento::etiquetaEstado($recuento->estado);

        return "No se puede registrar una salida en el depósito {$depLabel} con fecha {$fechaMovFmt}: "
            ."existe el recuento {$recuento->codigo} ({$estado}) con fecha {$fechaRc}. "
            .'Cierre, anule o cambie la fecha del recuento antes de sacar mercadería.';
    }

    private static function normalizarFecha(?string $fechaMovimiento): string
    {
        if ($fechaMovimiento) {
            return Carbon::parse($fechaMovimiento)->toDateString();
        }

        return now()->toDateString();
    }
}

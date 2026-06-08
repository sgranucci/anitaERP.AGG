<?php

namespace App\Support\Ventas\Gastronomia;

use App\Models\Ventas\Venta;

/**
 * Numeración secuencial ERP para emisión multi-factura del cierre Waitry (una transacción).
 *
 * Evita reconsultar Anita/ARCA en cada lote: el max local avanza con cada venta grabada
 * en la misma corrida (visible dentro de la transacción abierta).
 *
 * En PV CAEA (mod A) el piso debe incluir el último número de Anita cuando supera al ERP
 * (p. ej. ventas borradas solo en Anita o desfasaje histórico).
 */
final class CierreJornadaProcesoFacturaNumeracionSupport
{
    private int $ultimoNumero;

    public function __construct(int $puntoventaId, int $tipotransaccionId, int $pisoNumeracionExterno = 0)
    {
        $erpMax = self::maxNumerocomprobanteErp($puntoventaId, $tipotransaccionId);
        $this->ultimoNumero = max($erpMax, max(0, $pisoNumeracionExterno));
    }

    public function siguiente(): int
    {
        return ++$this->ultimoNumero;
    }

    public static function maxNumerocomprobanteErp(int $puntoventaId, int $tipotransaccionId): int
    {
        if ($puntoventaId <= 0 || $tipotransaccionId <= 0) {
            return 0;
        }

        return (int) (Venta::query()
            ->where('puntoventa_id', $puntoventaId)
            ->where('tipotransaccion_id', $tipotransaccionId)
            ->max('numerocomprobante') ?? 0);
    }
}

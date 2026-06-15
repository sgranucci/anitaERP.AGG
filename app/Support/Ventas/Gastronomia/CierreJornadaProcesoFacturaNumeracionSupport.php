<?php

namespace App\Support\Ventas\Gastronomia;

use App\Support\Ventas\VentaNumeracionEmpresaSupport;

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

    public function __construct(
        int $puntoventaId,
        int $tipotransaccionId,
        int $pisoNumeracionExterno = 0,
        ?int $empresaId = null,
    ) {
        $erpMax = self::maxNumerocomprobanteErp($puntoventaId, $tipotransaccionId, $empresaId);
        $this->ultimoNumero = max($erpMax, max(0, $pisoNumeracionExterno));
    }

    public function siguiente(): int
    {
        return ++$this->ultimoNumero;
    }

    public static function maxNumerocomprobanteErp(
        int $puntoventaId,
        int $tipotransaccionId,
        ?int $empresaId = null,
    ): int {
        return VentaNumeracionEmpresaSupport::maxNumerocomprobanteErp(
            $puntoventaId,
            $tipotransaccionId,
            $empresaId,
        );
    }
}

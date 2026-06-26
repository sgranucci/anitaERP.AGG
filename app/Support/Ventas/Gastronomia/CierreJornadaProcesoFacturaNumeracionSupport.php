<?php

namespace App\Support\Ventas\Gastronomia;

use App\Support\Ventas\VentaNumeracionEmpresaSupport;

/**
 * Numeración secuencial ERP para emisión multi-factura del cierre Waitry (una transacción).
 *
 * Evita reconsultar ARCA en cada lote: el max local avanza con cada venta grabada
 * en la misma corrida (visible dentro de la transacción abierta).
 */
final class CierreJornadaProcesoFacturaNumeracionSupport
{
    private int $ultimoNumero;

    public function __construct(
        int $puntoventaId,
        int|string $codigoAlmacenadoTipotransaccion,
        string $letraComprobante,
        int $pisoNumeracionExterno = 0,
        ?int $empresaId = null,
        ?string $modoFacturacionCliente = null,
        ?float $totalComprobante = null,
    ) {
        $erpMax = self::maxNumerocomprobanteErp(
            $puntoventaId,
            $codigoAlmacenadoTipotransaccion,
            $letraComprobante,
            $empresaId,
            $modoFacturacionCliente,
            $totalComprobante,
        );
        $this->ultimoNumero = max($erpMax, max(0, $pisoNumeracionExterno));
    }

    public function siguiente(): int
    {
        return ++$this->ultimoNumero;
    }

    public static function maxNumerocomprobanteErp(
        int $puntoventaId,
        int|string $codigoAlmacenadoTipotransaccion,
        string $letraComprobante,
        ?int $empresaId = null,
        ?string $modoFacturacionCliente = null,
        ?float $totalComprobante = null,
    ): int {
        if ($puntoventaId <= 0 || (int) preg_replace('/\D+/', '', (string) $codigoAlmacenadoTipotransaccion) <= 0) {
            return 0;
        }

        return VentaNumeracionEmpresaSupport::maxNumerocomprobanteErpDesdeTipotransaccion(
            $puntoventaId,
            $codigoAlmacenadoTipotransaccion,
            $letraComprobante,
            $empresaId,
            $modoFacturacionCliente,
            $totalComprobante,
        );
    }
}

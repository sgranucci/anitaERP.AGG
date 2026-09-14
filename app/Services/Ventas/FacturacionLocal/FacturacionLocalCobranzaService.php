<?php

namespace App\Services\Ventas\FacturacionLocal;

use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\Venta;
use App\Services\Caja\CobranzaService;
use App\Support\Caja\CotizacionTesoreriaConsultaSupport;
use InvalidArgumentException;

/**
 * Cobranza del POS Local reutilizando CobranzaService::guardaCobranzaGastronomia (payload genérico).
 */
final class FacturacionLocalCobranzaService
{
    private const MONEDA_PESOS_ID = 1;

    public function __construct(
        private readonly CobranzaService $cobranzaService,
    ) {
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id?:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     * @return array{cobranza_id:int,caja_movimiento_id?:int}
     */
    public function registrar(
        Venta $venta,
        LocalVenta $local,
        array $mediosPago,
        bool $esDevolucion = false,
    ): array {
        if ($mediosPago === []) {
            throw new InvalidArgumentException('Indique al menos un medio de cobro.');
        }

        $tipoCajaId = $esDevolucion ? $local->tipoCajaDevolucionId() : $local->tipoCajaId();
        if ($tipoCajaId <= 0) {
            throw new InvalidArgumentException('Configure el tipo de transacción de caja del local.');
        }

        $empresaId = (int) ($local->empresa_id ?: $venta->empresa_id ?: 0);
        $lineas = [];
        $total = 0.;
        foreach ($mediosPago as $medio) {
            $cuentacajaId = (int) ($medio['cuentacaja_id'] ?? 0);
            $monedaId = (int) ($medio['moneda_id'] ?? self::MONEDA_PESOS_ID);
            $monto = (float) ($medio['monto'] ?? 0);
            if ($cuentacajaId <= 0 || $monto <= 0.) {
                throw new InvalidArgumentException('Cada medio debe tener cuenta de caja y monto > 0.');
            }
            $cotizacion = isset($medio['cotizacion']) && (float) $medio['cotizacion'] > 0
                ? (float) $medio['cotizacion']
                : $this->cotizacion($venta->fecha, $monedaId, $empresaId);
            $lineas[] = [
                'cuentacaja_id' => $cuentacajaId,
                'moneda_id' => $monedaId,
                'monto' => $monto,
                'cotizacion' => $cotizacion,
                'observacion' => trim((string) ($medio['observacion'] ?? '')) ?: 'Facturación Local',
            ];
            $total += $monto * $cotizacion;
        }

        return $this->cobranzaService->guardaCobranzaGastronomia([
            'venta' => $venta,
            'empresa_id' => $empresaId > 0 ? $empresaId : (int) $venta->empresa_id,
            'tipotransaccion_caja_id' => $tipoCajaId,
            'totalfinalcobranza' => round($total, 2),
            'monedafinalcobranza_id' => self::MONEDA_PESOS_ID,
            'cotizacion_cobranza' => 1.,
            'lineas' => $lineas,
            'genera_contabilidad' => (bool) config('facturacion_local.genera_contabilidad_cobranza', false),
            'detalle' => 'Cobranza Facturación Local — '.$venta->codigo,
        ]);
    }

    private function cotizacion($fecha, int $monedaId, int $empresaId): float
    {
        if ($monedaId <= 1) {
            return 1.;
        }
        $ymd = is_string($fecha) ? $fecha : (string) ($fecha?->format('Y-m-d') ?? date('Y-m-d'));

        return (float) (CotizacionTesoreriaConsultaSupport::ventaPorMonedaId($ymd, $monedaId, $empresaId) ?: 1.);
    }
}

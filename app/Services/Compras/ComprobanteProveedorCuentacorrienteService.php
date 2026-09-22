<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Cuota;
use App\Repositories\Compras\Proveedor_CuentacorrienteRepositoryInterface;
use App\Support\Compras\ComprobanteProveedorCuotasTotalSupport;
use App\Support\Compras\ComprobanteProveedorFechaContableSupport;
use App\Support\Compras\ComprobanteProveedorVencimientoCondicionSupport;
use RuntimeException;

class ComprobanteProveedorCuentacorrienteService
{
    public function __construct(
        private Proveedor_CuentacorrienteRepositoryInterface $cuentacorrienteRepository,
        private ComprobanteProveedorCondicionPagoDesdeOcService $condicionPagoDesdeOc,
    ) {}

    public function generarDesdeComprobante(Comprobante_Proveedor $comprobante): void
    {
        $comprobante->loadMissing(['comprobante_proveedor_cuotas', 'tipotransaccion_compras', 'ordencompras']);

        if ($comprobante->comprobante_proveedor_cuotas->isEmpty()) {
            $this->autogenerarCuotasSiFaltan($comprobante);
            $comprobante->load('comprobante_proveedor_cuotas');
        }

        if ($comprobante->comprobante_proveedor_cuotas->isEmpty()) {
            throw new RuntimeException('No hay cuotas para registrar en cuenta corriente del proveedor.');
        }

        $signo = (string) ($comprobante->tipotransaccion_compras?->signo ?? 'S') === 'R' ? -1 : 1;
        $fecha = ComprobanteProveedorFechaContableSupport::fechaYmd($comprobante);

        $monedaFacturaId = (int) ($comprobante->moneda_id ?: 1);
        $cotizacionFactura = (float) ($comprobante->cotizacion ?: 1);

        $cantidadCuotas = $comprobante->comprobante_proveedor_cuotas->count();

        foreach ($comprobante->comprobante_proveedor_cuotas as $cuota) {
            if ($cuota->proveedor_cuentacorriente_id) {
                continue;
            }

            $montoCuota = (float) ($cuota->monto ?? 0);
            // Fallback al total de la factura solo con una cuota sin monto (alta incompleta).
            // Con varias cuotas, monto 0 = pagada/anulada/refinanciada: no genera deuda.
            if (abs($montoCuota) < 0.0001) {
                if ($cantidadCuotas === 1) {
                    $montoCuota = (float) ($comprobante->total ?? 0);
                } else {
                    continue;
                }
            }

            if (abs($montoCuota) < 0.0001) {
                continue;
            }

            $cc = $this->cuentacorrienteRepository->create([
                'fecha' => $fecha,
                'fechavencimiento' => $cuota->fechavencimiento?->format('Y-m-d') ?? $fecha,
                'proveedor_id' => $comprobante->proveedor_id,
                'total' => round($montoCuota * $signo, 4),
                // Siempre moneda/cotización de la factura (no ME residual de la OC).
                'moneda_id' => $monedaFacturaId,
                'cotizacion' => $cotizacionFactura,
                'empresa_id' => $comprobante->empresa_id,
                'comprobante_proveedor_id' => $comprobante->id,
                'comprobante_proveedor_cuota_id' => $cuota->id,
            ]);

            Comprobante_Proveedor_Cuota::query()
                ->where('id', $cuota->id)
                ->update(['proveedor_cuentacorriente_id' => $cc->id]);
        }
    }

    private function autogenerarCuotasSiFaltan(Comprobante_Proveedor $comprobante): void
    {
        $monedaFacturaId = (int) ($comprobante->moneda_id ?: 1);
        $cotizacionFactura = (float) ($comprobante->cotizacion ?: 1);
        $fechaBase = $comprobante->fechacomprobante?->format('Y-m-d') ?? now()->format('Y-m-d');
        $cuotas = [];

        if ($comprobante->ordencompra_id && $comprobante->ordencompras) {
            $meta = $this->condicionPagoDesdeOc->resolverDesdeOrdencompra(
                $comprobante->ordencompras,
                $comprobante->ordencompra_comprobante_id,
                (float) $comprobante->total,
                $fechaBase,
                $monedaFacturaId,
                $cotizacionFactura,
            );
            $cuotas = $meta['cuotas'];

            if (! $comprobante->condicionpago_id && ! empty($meta['condicionpago_id'])) {
                $comprobante->forceFill(['condicionpago_id' => $meta['condicionpago_id']])->save();
            }
        }

        // Sin OC o sin plan usable: armar desde condición de pago, o una cuota al total.
        if ($cuotas === [] && abs((float) ($comprobante->total ?? 0)) >= 0.0001) {
            if ($comprobante->condicionpago_id) {
                $cuotas = ComprobanteProveedorVencimientoCondicionSupport::armarCuotasDesdeCondicion(
                    (int) $comprobante->condicionpago_id,
                    $fechaBase,
                    (float) $comprobante->total,
                    $monedaFacturaId,
                    $cotizacionFactura,
                );
            }
            if ($cuotas === []) {
                $cuotas[] = [
                    'numero_cuota' => 1,
                    'fechavencimiento' => $fechaBase,
                    'monto' => round((float) $comprobante->total, 2),
                    'formapago_id' => 1,
                    'detalle' => null,
                    'ordencompra_comprobante_cuota_id' => null,
                ];
            }
        } else {
            $cuotas = ComprobanteProveedorVencimientoCondicionSupport::aplicarACuotas(
                $cuotas,
                $comprobante->condicionpago_id ? (int) $comprobante->condicionpago_id : null,
                $fechaBase,
            );
        }

        $cuotas = ComprobanteProveedorCuotasTotalSupport::alinearConTotalSiHaceFalta(
            $cuotas,
            (float) ($comprobante->total ?? 0),
        );

        foreach ($cuotas as $cuota) {
            Comprobante_Proveedor_Cuota::query()->create([
                'comprobante_proveedor_id' => $comprobante->id,
                'numero_cuota' => (int) ($cuota['numero_cuota'] ?? 1),
                'fechavencimiento' => $cuota['fechavencimiento'],
                'monto' => (float) ($cuota['monto'] ?? 0),
                'moneda_id' => $monedaFacturaId,
                'cotizacion' => $cotizacionFactura,
                'formapago_id' => (int) ($cuota['formapago_id'] ?? 1),
                'detalle' => $cuota['detalle'] ?? null,
                'ordencompra_comprobante_cuota_id' => $cuota['ordencompra_comprobante_cuota_id'] ?? null,
            ]);
        }
    }
}

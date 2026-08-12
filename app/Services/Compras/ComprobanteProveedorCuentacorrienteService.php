<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Cuota;
use App\Repositories\Compras\Proveedor_CuentacorrienteRepositoryInterface;
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
        $fecha = $comprobante->fechacomprobante?->format('Y-m-d') ?? now()->format('Y-m-d');

        $monedaFacturaId = (int) ($comprobante->moneda_id ?: 1);
        $cotizacionFactura = (float) ($comprobante->cotizacion ?: 1);

        foreach ($comprobante->comprobante_proveedor_cuotas as $cuota) {
            if ($cuota->proveedor_cuentacorriente_id) {
                continue;
            }

            $montoCuota = (float) ($cuota->monto ?? 0);
            if (abs($montoCuota) < 0.0001) {
                $montoCuota = (float) ($comprobante->total ?? 0);
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
        if (! $comprobante->ordencompra_id || ! $comprobante->ordencompras) {
            return;
        }

        $monedaFacturaId = (int) ($comprobante->moneda_id ?: 1);
        $cotizacionFactura = (float) ($comprobante->cotizacion ?: 1);

        $meta = $this->condicionPagoDesdeOc->resolverDesdeOrdencompra(
            $comprobante->ordencompras,
            $comprobante->ordencompra_comprobante_id,
            (float) $comprobante->total,
            $comprobante->fechacomprobante?->format('Y-m-d') ?? now()->format('Y-m-d'),
            $monedaFacturaId,
            $cotizacionFactura,
        );

        foreach ($meta['cuotas'] as $cuota) {
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

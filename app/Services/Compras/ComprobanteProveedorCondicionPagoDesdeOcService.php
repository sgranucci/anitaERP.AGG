<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Cuota;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Comprobante;
use App\Support\Compras\ComprobanteProveedorCuotasTotalSupport;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\ComprobanteProveedorVencimientoCondicionSupport;
use Carbon\Carbon;

/**
 * Arma el plan de cuotas del comprobante desde la OC (montos / forma de pago)
 * y aplica vencimientos desde la condición de pago (F.Comp. + plazo en días).
 */
class ComprobanteProveedorCondicionPagoDesdeOcService
{
    /**
     * @return array{
     *     condicionpago_id: int|null,
     *     ordencompra_comprobante_id: int|null,
     *     cuotas: list<array{
     *         numero_cuota: int,
     *         fechavencimiento: string,
     *         monto: float,
     *         moneda_id: int,
     *         cotizacion: float|null,
     *         formapago_id: int,
     *         detalle: string|null,
     *         ordencompra_comprobante_cuota_id: int|null
     *     }>,
     *     cuotas_escaladas: bool,
     *     permite_edicion_cuotas: bool
     * }
     */
    public function resolverDesdeOrdencompra(
        Ordencompra $ordencompra,
        ?int $ordencompraComprobanteId,
        float $totalComprobante,
        string $fechaBase,
        ?int $monedaFacturaId = null,
        ?float $cotizacionFactura = null,
    ): array {
        $ocComprobante = null;
        $vinculoExplicito = false;
        if ($ordencompraComprobanteId) {
            $ocComprobante = Ordencompra_Comprobante::query()
                ->with('ordencompra_comprobante_cuotas')
                ->where('ordencompra_id', $ordencompra->id)
                ->find($ordencompraComprobanteId);
            $vinculoExplicito = $ocComprobante !== null;
        }

        // Sin id explícito: próximo comprobante a venir aún no facturado (por vencimiento).
        if (! $ocComprobante) {
            $ocComprobante = Ordencompra_Comprobante::query()
                ->with('ordencompra_comprobante_cuotas')
                ->where('ordencompra_id', $ordencompra->id)
                ->pendientesDeFacturar()
                ->orderBy('fechavencimiento')
                ->orderBy('id')
                ->first();
        }

        if ($ocComprobante && ! $ocComprobante->ordencompra_comprobante_cuotas->isEmpty()) {
            return $this->conVencimientosDesdeCondicion(
                $this->desdeCuotasOc(
                    $ocComprobante,
                    $totalComprobante,
                    $monedaFacturaId,
                    $cotizacionFactura,
                    vincularOcc: true,
                ),
                $fechaBase,
                $totalComprobante,
                $monedaFacturaId,
                $cotizacionFactura,
            );
        }

        // OCC ya usado / sin pendiente: repetir plan de la factura anterior del mismo legajo.
        $desdeAnterior = $this->fallbackDesdeCuotaAnterior(
            $ordencompra,
            $vinculoExplicito ? $ocComprobante : null,
            $totalComprobante,
            $fechaBase,
            $monedaFacturaId,
            $cotizacionFactura,
        );
        if ($desdeAnterior['cuotas'] !== []) {
            return $this->conVencimientosDesdeCondicion(
                $desdeAnterior,
                $fechaBase,
                $totalComprobante,
                $monedaFacturaId,
                $cotizacionFactura,
            );
        }

        // Último recurso: OCC del legajo aunque ya esté facturado (solo plantilla, sin re-vincular).
        $occPlantilla = Ordencompra_Comprobante::query()
            ->with('ordencompra_comprobante_cuotas')
            ->where('ordencompra_id', $ordencompra->id)
            ->whereHas('ordencompra_comprobante_cuotas')
            ->orderByDesc('id')
            ->first();

        if ($occPlantilla && ! $occPlantilla->ordencompra_comprobante_cuotas->isEmpty()) {
            $meta = $this->desdeCuotasOc(
                $occPlantilla,
                $totalComprobante,
                $monedaFacturaId,
                $cotizacionFactura,
                vincularOcc: false,
            );
            if ($vinculoExplicito && $ocComprobante) {
                $meta['ordencompra_comprobante_id'] = $ocComprobante->id;
                $meta['condicionpago_id'] = $ocComprobante->condicionpago_id ?: $meta['condicionpago_id'];
            }

            return $this->conVencimientosDesdeCondicion(
                $meta,
                $fechaBase,
                $totalComprobante,
                $monedaFacturaId,
                $cotizacionFactura,
            );
        }

        $condicionId = $vinculoExplicito
            ? ($ocComprobante?->condicionpago_id ?: $ordencompra->condicionpago_id)
            : $ordencompra->condicionpago_id;

        return $this->conVencimientosDesdeCondicion(
            [
                'condicionpago_id' => $condicionId,
                'ordencompra_comprobante_id' => $vinculoExplicito ? $ocComprobante?->id : null,
                'cuotas' => [],
                'cuotas_escaladas' => false,
                'permite_edicion_cuotas' => true,
            ],
            $fechaBase,
            $totalComprobante,
            $monedaFacturaId,
            $cotizacionFactura,
        );
    }

    /**
     * Aplica F.Comp. + plazo de la condición; si no hay cuotas, las arma desde la plantilla.
     *
     * @param  array{
     *     condicionpago_id: int|null,
     *     ordencompra_comprobante_id: int|null,
     *     cuotas: list<array<string, mixed>>,
     *     cuotas_escaladas: bool,
     *     permite_edicion_cuotas: bool
     * }  $meta
     * @return array{
     *     condicionpago_id: int|null,
     *     ordencompra_comprobante_id: int|null,
     *     cuotas: list<array<string, mixed>>,
     *     cuotas_escaladas: bool,
     *     permite_edicion_cuotas: bool
     * }
     */
    public function conVencimientosDesdeCondicion(
        array $meta,
        string $fechaBase,
        float $totalComprobante = 0.0,
        ?int $monedaFacturaId = null,
        ?float $cotizacionFactura = null,
    ): array {
        $condicionId = isset($meta['condicionpago_id']) ? (int) $meta['condicionpago_id'] : 0;
        $condicionId = $condicionId > 0 ? $condicionId : null;
        $cuotas = $meta['cuotas'] ?? [];

        if ($cuotas === [] && $condicionId && abs($totalComprobante) >= 0.0001) {
            $cuotas = ComprobanteProveedorVencimientoCondicionSupport::armarCuotasDesdeCondicion(
                $condicionId,
                $fechaBase,
                $totalComprobante,
                (int) ($monedaFacturaId ?: 1),
                (float) ($cotizacionFactura ?? 1),
            );
            if ($cuotas !== []) {
                $meta['cuotas'] = $cuotas;
                $meta['cuotas_escaladas'] = false;
            }

            return $meta;
        }

        $meta['cuotas'] = ComprobanteProveedorVencimientoCondicionSupport::aplicarACuotas(
            $cuotas,
            $condicionId,
            $fechaBase,
        );

        return $meta;
    }

    /**
     * Persiste cuotas en el comprobante (reemplaza las existentes).
     *
     * @param  list<array<string, mixed>>  $cuotasPayload
     */
    public function sincronizarCuotasComprobante(int $comprobanteProveedorId, array $cuotasPayload): void
    {
        Comprobante_Proveedor_Cuota::query()
            ->where('comprobante_proveedor_id', $comprobanteProveedorId)
            ->delete();

        foreach ($cuotasPayload as $cuota) {
            Comprobante_Proveedor_Cuota::create([
                'comprobante_proveedor_id' => $comprobanteProveedorId,
                'numero_cuota' => (int) $cuota['numero_cuota'],
                'fechavencimiento' => $cuota['fechavencimiento'],
                'monto' => $cuota['monto'],
                'moneda_id' => (int) $cuota['moneda_id'],
                'cotizacion' => $cuota['cotizacion'] ?? null,
                'formapago_id' => (int) $cuota['formapago_id'],
                'detalle' => $cuota['detalle'] ?? null,
                'ordencompra_comprobante_cuota_id' => $cuota['ordencompra_comprobante_cuota_id'] ?? null,
                'total_pagado' => 0,
            ]);
        }
    }

    /**
     * @return array{
     *     condicionpago_id: int|null,
     *     ordencompra_comprobante_id: int|null,
     *     cuotas: list<array<string, mixed>>,
     *     cuotas_escaladas: bool,
     *     permite_edicion_cuotas: bool
     * }
     */
    private function desdeCuotasOc(
        Ordencompra_Comprobante $ocComprobante,
        float $totalComprobante,
        ?int $monedaFacturaId,
        ?float $cotizacionFactura,
        bool $vincularOcc,
    ): array {
        $cuotasOc = $ocComprobante->ordencompra_comprobante_cuotas->sortBy('id')->values();
        $sumOc = (float) $cuotasOc->sum('monto');
        // totalComprobante ya está en moneda factura: el factor escala importes a esa moneda.
        $factor = $sumOc > 0 ? $totalComprobante / $sumOc : 0.0;

        $cuotas = [];
        $n = 1;
        $ultimo = $cuotasOc->count();
        $asignado = 0.0;
        foreach ($cuotasOc as $cuotaOc) {
            if ($sumOc > 0) {
                if ($n === $ultimo) {
                    $monto = round($totalComprobante - $asignado, 2);
                } else {
                    $monto = round((float) $cuotaOc->monto * $factor, 2);
                    $asignado += $monto;
                }
            } else {
                // OC sin montos: toda la factura en la primera cuota (moneda factura).
                $monto = $n === 1 ? round($totalComprobante, 2) : 0.0;
            }

            $cuotas[] = [
                'numero_cuota' => $n,
                'fechavencimiento' => $this->formatearFecha($cuotaOc->fechavencimiento),
                'monto' => $monto,
                // Moneda/cotización de la factura, no de la OC (puede ser ME con factura en pesos).
                'moneda_id' => (int) ($monedaFacturaId ?: $cuotaOc->moneda_id ?: 1),
                'cotizacion' => $cotizacionFactura !== null
                    ? (float) $cotizacionFactura
                    : ($cuotaOc->cotizacion !== null ? (float) $cuotaOc->cotizacion : null),
                'formapago_id' => (int) ($cuotaOc->formapago_id ?: 1),
                'detalle' => $cuotaOc->detalle,
                'ordencompra_comprobante_cuota_id' => $vincularOcc ? (int) $cuotaOc->id : null,
            ];
            $n++;
        }

        $cuotas = ComprobanteProveedorCuotasTotalSupport::alinearConTotalSiHaceFalta(
            $cuotas,
            $totalComprobante,
        );

        return [
            'condicionpago_id' => $ocComprobante->condicionpago_id,
            'ordencompra_comprobante_id' => $vincularOcc ? $ocComprobante->id : null,
            'cuotas' => $cuotas,
            'cuotas_escaladas' => abs($factor - 1.0) > 0.0001,
            'permite_edicion_cuotas' => true,
        ];
    }

    /**
     * Repite montos / forma de pago / detalle de la última factura del legajo,
     * reescalando al total. Los vencimientos se recalculan luego desde la condición.
     *
     * @return array{
     *     condicionpago_id: int|null,
     *     ordencompra_comprobante_id: int|null,
     *     cuotas: list<array<string, mixed>>,
     *     cuotas_escaladas: bool,
     *     permite_edicion_cuotas: bool
     * }
     */
    private function fallbackDesdeCuotaAnterior(
        Ordencompra $ordencompra,
        ?Ordencompra_Comprobante $ocComprobanteVinculado,
        float $totalComprobante,
        string $fechaBase,
        ?int $monedaFacturaId,
        ?float $cotizacionFactura,
    ): array {
        $anterior = Comprobante_Proveedor::query()
            ->with('comprobante_proveedor_cuotas')
            ->where('ordencompra_id', $ordencompra->id)
            ->where('estado', '!=', ComprobanteProveedorEstados::ANULADO)
            ->whereHas('comprobante_proveedor_cuotas')
            ->orderByDesc('id')
            ->first();

        if (! $anterior || $anterior->comprobante_proveedor_cuotas->isEmpty()) {
            return [
                'condicionpago_id' => $ocComprobanteVinculado?->condicionpago_id ?: $ordencompra->condicionpago_id,
                'ordencompra_comprobante_id' => $ocComprobanteVinculado?->id,
                'cuotas' => [],
                'cuotas_escaladas' => false,
                'permite_edicion_cuotas' => true,
            ];
        }

        $cuotasPrev = $anterior->comprobante_proveedor_cuotas->sortBy('numero_cuota')->values();
        $sumPrev = (float) $cuotasPrev->sum('monto');
        $factor = $sumPrev > 0 ? $totalComprobante / $sumPrev : 0.0;
        $monedaId = (int) ($monedaFacturaId ?: $anterior->moneda_id ?: 1);
        $cotizacion = $cotizacionFactura !== null
            ? (float) $cotizacionFactura
            : (float) ($anterior->cotizacion ?: 1);

        $cuotas = [];
        $n = 1;
        $ultimo = $cuotasPrev->count();
        $asignado = 0.0;
        foreach ($cuotasPrev as $cuotaPrev) {
            if ($sumPrev > 0) {
                if ($n === $ultimo) {
                    $monto = round($totalComprobante - $asignado, 2);
                } else {
                    $monto = round((float) $cuotaPrev->monto * $factor, 2);
                    $asignado += $monto;
                }
            } else {
                $monto = $n === 1 ? round($totalComprobante, 2) : 0.0;
            }

            $vto = $cuotaPrev->fechavencimiento
                ? $this->formatearFecha($cuotaPrev->fechavencimiento)
                : $fechaBase;

            $cuotas[] = [
                'numero_cuota' => $n,
                'fechavencimiento' => $vto,
                'monto' => $monto,
                'moneda_id' => $monedaId,
                'cotizacion' => $cotizacion,
                'formapago_id' => (int) ($cuotaPrev->formapago_id ?: 1),
                'detalle' => $cuotaPrev->detalle,
                // No reusa el id de cuota OCC/CP anterior (pertenece a otra factura).
                'ordencompra_comprobante_cuota_id' => null,
            ];
            $n++;
        }

        $cuotas = ComprobanteProveedorCuotasTotalSupport::alinearConTotalSiHaceFalta(
            $cuotas,
            $totalComprobante,
        );

        return [
            'condicionpago_id' => $anterior->condicionpago_id
                ?: $ocComprobanteVinculado?->condicionpago_id
                ?: $ordencompra->condicionpago_id,
            'ordencompra_comprobante_id' => $ocComprobanteVinculado?->id,
            'cuotas' => $cuotas,
            'cuotas_escaladas' => abs($factor - 1.0) > 0.0001,
            'permite_edicion_cuotas' => true,
        ];
    }

    private function formatearFecha(mixed $fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }

        return Carbon::parse((string) $fecha)->format('Y-m-d');
    }
}

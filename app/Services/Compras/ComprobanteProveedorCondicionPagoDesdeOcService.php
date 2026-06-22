<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor_Cuota;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Comprobante;
use Carbon\Carbon;

/**
 * Arma el plan de cuotas del comprobante desde la OC (comprobante a venir + condición de pago ERP).
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
     *     }>
     * }
     */
    public function resolverDesdeOrdencompra(
        Ordencompra $ordencompra,
        ?int $ordencompraComprobanteId,
        float $totalComprobante,
        string $fechaBase,
    ): array {
        $ocComprobante = null;
        if ($ordencompraComprobanteId) {
            $ocComprobante = Ordencompra_Comprobante::query()
                ->with('ordencompra_comprobante_cuotas')
                ->where('ordencompra_id', $ordencompra->id)
                ->find($ordencompraComprobanteId);
        }

        if (! $ocComprobante) {
            $ocComprobante = Ordencompra_Comprobante::query()
                ->with('ordencompra_comprobante_cuotas')
                ->where('ordencompra_id', $ordencompra->id)
                ->orderBy('id')
                ->first();
        }

        if (! $ocComprobante || $ocComprobante->ordencompra_comprobante_cuotas->isEmpty()) {
            return [
                'condicionpago_id' => $ocComprobante?->condicionpago_id,
                'ordencompra_comprobante_id' => $ocComprobante?->id,
                'cuotas' => [],
                'cuotas_escaladas' => false,
                'permite_edicion_cuotas' => true,
            ];
        }

        $cuotasOc = $ocComprobante->ordencompra_comprobante_cuotas->sortBy('id')->values();
        $sumOc = (float) $cuotasOc->sum('monto');
        $factor = $sumOc > 0 ? $totalComprobante / $sumOc : 1.0;

        $cuotas = [];
        $n = 1;
        foreach ($cuotasOc as $cuotaOc) {
            $cuotas[] = [
                'numero_cuota' => $n,
                'fechavencimiento' => $this->formatearFecha($cuotaOc->fechavencimiento),
                'monto' => round((float) $cuotaOc->monto * $factor, 4),
                'moneda_id' => (int) $cuotaOc->moneda_id,
                'cotizacion' => $cuotaOc->cotizacion !== null ? (float) $cuotaOc->cotizacion : null,
                'formapago_id' => (int) $cuotaOc->formapago_id,
                'detalle' => $cuotaOc->detalle,
                'ordencompra_comprobante_cuota_id' => (int) $cuotaOc->id,
            ];
            $n++;
        }

        return [
            'condicionpago_id' => $ocComprobante->condicionpago_id,
            'ordencompra_comprobante_id' => $ocComprobante->id,
            'cuotas' => $cuotas,
            'cuotas_escaladas' => abs($factor - 1.0) > 0.0001,
            'permite_edicion_cuotas' => true,
        ];
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

    private function formatearFecha(mixed $fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }

        return Carbon::parse((string) $fecha)->format('Y-m-d');
    }
}

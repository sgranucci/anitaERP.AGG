<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\VentaGastronomiaEmision;
use App\Support\Ventas\Gastronomia\VentaGastronomiaEmisionWaitrySupport;
use Illuminate\Database\Eloquent\Builder;

/**
 * Completa venta_gastronomia_emision.waitry_order_id desde cuenta_gastronomia o waitry_comanda_envio.
 */
final class GastronomiaBackfillWaitryEmisionOrderIdService
{
    /**
     * @param  array{
     *   empresa_id?:int|null,
     *   fecha_desde?:string|null,
     *   fecha_hasta?:string|null,
     *   limite?:int,
     *   dry_run?:bool,
     * }  $opciones
     * @return array{
     *   escaneadas:int,
     *   actualizadas:int,
     *   desde_cuenta:int,
     *   desde_envio:int,
     *   sin_fuente:int,
     *   conflictos:int,
     *   detalle:list<array{venta_id:int,waitry_order_id:int,origen:string,codigo:?string}>,
     *   conflictos_detalle:list<array{venta_id:int,waitry_order_id:int,venta_id_ocupante:int,codigo:?string}>
     * }
     */
    public function ejecutar(array $opciones = []): array
    {
        $empresaId = isset($opciones['empresa_id']) ? (int) $opciones['empresa_id'] : 0;
        $fechaDesde = trim((string) ($opciones['fecha_desde'] ?? ''));
        $fechaHasta = trim((string) ($opciones['fecha_hasta'] ?? ''));
        $limite = max(0, (int) ($opciones['limite'] ?? 0));
        $dryRun = ! empty($opciones['dry_run']);

        $stats = [
            'escaneadas' => 0,
            'actualizadas' => 0,
            'desde_cuenta' => 0,
            'desde_envio' => 0,
            'sin_fuente' => 0,
            'conflictos' => 0,
            'detalle' => [],
            'conflictos_detalle' => [],
        ];

        $query = $this->queryBase($empresaId, $fechaDesde, $fechaHasta);
        $procesadas = 0;

        $query->orderBy('venta_id')->chunkById(200, function ($emisiones) use (
            &$stats,
            &$procesadas,
            $limite,
            $dryRun,
        ) {
            foreach ($emisiones as $emision) {
                if ($limite > 0 && $procesadas >= $limite) {
                    return false;
                }

                $procesadas++;
                $stats['escaneadas']++;

                $resuelto = $this->resolverOrderIdYOrigen($emision);
                $orderId = (int) ($resuelto['waitry_order_id'] ?? 0);
                $origen = (string) ($resuelto['origen'] ?? '');

                if ($orderId <= 0) {
                    $stats['sin_fuente']++;

                    continue;
                }

                $ventaId = (int) $emision->venta_id;
                $ocupante = VentaGastronomiaEmisionWaitrySupport::ventaIdConWaitryOrderId($orderId, $ventaId);
                if ($ocupante !== null) {
                    $stats['conflictos']++;
                    if (count($stats['conflictos_detalle']) < 50) {
                        $stats['conflictos_detalle'][] = [
                            'venta_id' => $ventaId,
                            'waitry_order_id' => $orderId,
                            'venta_id_ocupante' => $ocupante,
                            'codigo' => $emision->venta?->codigo,
                        ];
                    }

                    continue;
                }

                if ($dryRun) {
                    $stats['actualizadas']++;
                } elseif (VentaGastronomiaEmisionWaitrySupport::persistirOrderIdEnEmision($ventaId, $orderId)) {
                    $stats['actualizadas']++;
                } else {
                    $stats['conflictos']++;

                    continue;
                }
                if ($origen === 'cuenta') {
                    $stats['desde_cuenta']++;
                } elseif ($origen === 'envio') {
                    $stats['desde_envio']++;
                }

                if (count($stats['detalle']) < 50) {
                    $stats['detalle'][] = [
                        'venta_id' => (int) $emision->venta_id,
                        'waitry_order_id' => $orderId,
                        'origen' => $origen,
                        'codigo' => $emision->venta?->codigo,
                    ];
                }
            }

            return ! ($limite > 0 && $procesadas >= $limite);
        }, 'venta_id');

        return $stats;
    }

    /**
     * @return array{waitry_order_id:int,origen:string}
     */
    public function resolverOrderIdYOrigen(VentaGastronomiaEmision $emision): array
    {
        $desdeEmision = (int) ($emision->waitry_order_id ?? 0);
        if ($desdeEmision > 0) {
            return ['waitry_order_id' => $desdeEmision, 'origen' => 'emision'];
        }

        $desdeCuenta = (int) ($emision->cuenta?->waitry_order_id ?? 0);
        if ($desdeCuenta > 0) {
            return ['waitry_order_id' => $desdeCuenta, 'origen' => 'cuenta'];
        }

        $desdeEnvio = (int) ($emision->waitryComandaEnvio?->waitry_order_id ?? 0);
        if ($desdeEnvio > 0) {
            return ['waitry_order_id' => $desdeEnvio, 'origen' => 'envio'];
        }

        return ['waitry_order_id' => 0, 'origen' => ''];
    }

    private function queryBase(int $empresaId, string $fechaDesde, string $fechaHasta): Builder
    {
        return VentaGastronomiaEmision::query()
            ->with(['venta:id,codigo,fechajornada', 'cuenta:id,waitry_order_id', 'waitryComandaEnvio'])
            ->whereNull('waitry_order_id')
            ->when($empresaId > 0, function (Builder $q) use ($empresaId) {
                $q->whereHas('venta.puntoventas', fn (Builder $pv) => $pv->where('empresa_id', $empresaId));
            })
            ->when($fechaDesde !== '', function (Builder $q) use ($fechaDesde) {
                $q->whereHas('venta', fn (Builder $v) => $v->whereDate('fechajornada', '>=', $fechaDesde));
            })
            ->when($fechaHasta !== '', function (Builder $q) use ($fechaHasta) {
                $q->whereHas('venta', fn (Builder $v) => $v->whereDate('fechajornada', '<=', $fechaHasta));
            });
    }
}

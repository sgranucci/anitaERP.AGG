<?php

namespace App\Services\Ventas\Gastronomia;

use App\Console\Commands\LimpiarVentasPruebaGastronomia;
use App\Models\Ventas\GastronomiaCierreJornadaProcesoSnapshot;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\Venta;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Services\Stock\MovimientoStockService;
use App\Services\Ventas\FacturacionService;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoFacturaRecuperacionSupport;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Revierte facturas CF del proceso, asientos contables y ajuste de insumos para rehacer el cierre Waitry.
 */
final class GastronomiaCierreJornadaProcesoReversionService
{
    public function __construct(
        private readonly AsientoRepositoryInterface $asientoRepository,
        private readonly FacturacionService $facturacionService,
        private readonly MovimientoStockService $movimientoStockService,
        private readonly LimpiarVentasPruebaGastronomia $limpiarVentas,
        private readonly GastronomiaCierreJornadaProcesoRendicionAnitaService $rendicionAnitaService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function resumenDesdeSnapshot(?GastronomiaCierreJornadaProcesoSnapshot $snapshot): array
    {
        $payload = is_array($snapshot?->payload) ? $snapshot->payload : [];
        $emision = is_array($payload['factura_proceso_emision'] ?? null)
            ? $payload['factura_proceso_emision']
            : [];
        $grabacion = is_array($payload['asientos_proceso_grabacion'] ?? null)
            ? $payload['asientos_proceso_grabacion']
            : [];
        $ajuste = is_array($emision['ajuste_insumos'] ?? null) ? $emision['ajuste_insumos'] : null;
        $rendicion = is_array($payload['rendicion_proceso_anita'] ?? null)
            ? $payload['rendicion_proceso_anita']
            : null;

        $facturas = [];
        foreach ($emision['facturas'] ?? [] as $fac) {
            if (! is_array($fac)) {
                continue;
            }
            $facturas[] = [
                'lote' => (int) ($fac['lote'] ?? 0),
                'venta_id' => (int) ($fac['venta_id'] ?? 0),
                'factura' => (string) ($fac['factura'] ?? ''),
                'total' => round((float) ($fac['total'] ?? 0), 2),
            ];
        }

        if ($facturas === [] && ! empty($emision['venta_id'])) {
            $facturas[] = [
                'lote' => 1,
                'venta_id' => (int) $emision['venta_id'],
                'factura' => (string) ($emision['factura'] ?? ''),
                'total' => round((float) ($emision['total_factura'] ?? 0), 2),
            ];
        }

        $asientos = [];
        foreach ($grabacion['asientos'] ?? [] as $asi) {
            if (! is_array($asi)) {
                continue;
            }
            $asientos[] = [
                'codigo' => (string) ($asi['codigo'] ?? ''),
                'titulo' => (string) ($asi['titulo'] ?? ''),
                'asiento_id' => (int) ($asi['asiento_id'] ?? 0),
                'numeroasiento' => (string) ($asi['numeroasiento'] ?? ''),
            ];
        }

        return [
            'tiene_emision' => $facturas !== [],
            'tiene_asientos' => $asientos !== [],
            'tiene_ajuste_insumos' => is_array($ajuste) && (int) ($ajuste['movimientostock_id'] ?? 0) > 0,
            'tiene_rendicion_anita' => is_array($rendicion) && (int) ($rendicion['nro_oper'] ?? 0) > 0,
            'facturas' => $facturas,
            'asientos' => $asientos,
            'ajuste_insumos' => $ajuste,
            'rendicion_anita' => $rendicion,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function revertir(int $jornadaId): array
    {
        $jornada = JornadaGastronomia::query()->findOrFail($jornadaId);
        $snapshot = GastronomiaCierreJornadaProcesoSnapshot::query()
            ->where('jornada_gastronomia_id', $jornadaId)
            ->first();

        if ($snapshot === null) {
            throw new InvalidArgumentException('No hay snapshot de proceso para esta jornada.');
        }

        $payload = is_array($snapshot->payload) ? $snapshot->payload : [];
        $resumen = $this->resumenDesdeSnapshot($snapshot);

        if (! $resumen['tiene_emision'] && ! $resumen['tiene_asientos'] && ! $resumen['tiene_rendicion_anita']) {
            throw new InvalidArgumentException(
                'No hay facturas del proceso, asientos grabados ni rendición Anita para revertir en esta jornada.',
            );
        }

        $recuperacion = self::ventaIdsEmisionActivaDesdePayload($payload);
        $ventaIds = $recuperacion['venta_ids'];

        $asientosEliminados = [];
        $facturasEliminadas = [];
        $rendicionEliminada = null;
        $errores = [];

        try {
            DB::transaction(function () use (
                $snapshot,
                $payload,
                $resumen,
                $ventaIds,
                &$asientosEliminados,
                &$facturasEliminadas,
                &$rendicionEliminada,
                &$errores,
            ) {
                if ($resumen['tiene_rendicion_anita']) {
                    try {
                        $jornada = JornadaGastronomia::query()->find($snapshot->jornada_gastronomia_id);
                        $rendicionEliminada = $this->rendicionAnitaService->revertirDesdePayload(
                            $payload,
                            $jornada,
                        );
                    } catch (Throwable $e) {
                        $errores[] = 'Rendición Anita: '.$e->getMessage();
                        throw $e;
                    }
                }

                foreach ($resumen['asientos'] as $asi) {
                    $asientoId = (int) ($asi['asiento_id'] ?? 0);
                    if ($asientoId <= 0) {
                        continue;
                    }
                    try {
                        $this->eliminarAsientoProceso($asientoId);
                        $asientosEliminados[] = $asi;
                    } catch (Throwable $e) {
                        $errores[] = 'Asiento #'.$asientoId.': '.$e->getMessage();
                        throw $e;
                    }
                }

                $movId = (int) ($resumen['ajuste_insumos']['movimientostock_id'] ?? 0);
                if ($movId > 0) {
                    $this->movimientoStockService->borraMovimientoStock($movId);
                }

                foreach ($ventaIds as $ventaId) {
                    try {
                        $this->eliminarVentaProcesoEnErpYAnita($ventaId);
                        $facturasEliminadas[] = $ventaId;
                    } catch (Throwable $e) {
                        $errores[] = 'Venta #'.$ventaId.': '.$e->getMessage();
                        throw $e;
                    }
                }

                $emisionActiva = is_array($payload['factura_proceso_emision'] ?? null)
                    ? $payload['factura_proceso_emision']
                    : null;
                if ($emisionActiva !== null && ($emisionActiva['facturas'] ?? $emisionActiva['venta_id'] ?? null)) {
                    $payload['factura_proceso_emision_recuperacion'] = $emisionActiva;
                }

                unset(
                    $payload['factura_proceso_emision'],
                    $payload['asientos_proceso_grabacion'],
                    $payload['rendicion_proceso_anita'],
                );
                $snapshot->payload = $payload;
                $snapshot->save();
            });
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Error al revertir el proceso de cierre: '.$e->getMessage(),
                0,
                $e,
            );
        }

        return [
            'ok' => true,
            'mensaje' => 'Proceso revertido. Puede volver a emitir facturas y grabar asientos.',
            'asientos_eliminados' => count($asientosEliminados),
            'facturas_eliminadas' => count($facturasEliminadas),
            'detalle_asientos' => $asientosEliminados,
            'detalle_facturas_venta_ids' => $facturasEliminadas,
            'rendicion_anita_eliminada' => $rendicionEliminada,
            'errores' => $errores,
        ];
    }

    private function eliminarAsientoProceso(int $asientoId): void
    {
        DB::table('asiento_archivo')->where('asiento_id', $asientoId)->delete();
        $this->asientoRepository->delete($asientoId);
    }

    private function eliminarVentaProcesoEnErpYAnita(int $ventaId): void
    {
        $venta = Venta::query()->find($ventaId);
        if ($venta === null) {
            return;
        }

        try {
            $this->facturacionService->borraAnitaDesdeVenta($venta, true);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'No se pudo borrar la factura en Anita ('.$venta->codigo.'): '.$e->getMessage(),
                0,
                $e,
            );
        }

        $this->limpiarVentas->eliminarVentaPorId($ventaId);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{venta_ids: list<int>}
     */
    private static function ventaIdsEmisionActivaDesdePayload(array $payload): array
    {
        $emision = $payload['factura_proceso_emision'] ?? null;
        if (! is_array($emision)) {
            return ['venta_ids' => []];
        }

        $ventaIds = CierreJornadaProcesoFacturaRecuperacionSupport::ventaIdsDesdeRecuperacion($emision);
        if ($ventaIds === []) {
            $legacyVentaId = (int) ($emision['venta_id'] ?? 0);
            if ($legacyVentaId > 0) {
                $ventaIds = [$legacyVentaId];
            }
        }

        return ['venta_ids' => $ventaIds];
    }
}

<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaAnitaReplica;
use App\Support\Ventas\PedidoFacturaAnitaArchivosSupport;
use App\Support\Ventas\PedidoFacturaAnitaSemaforoSupport;
use Illuminate\Support\Facades\Log;

/**
 * Semáforo + regrabación de facturas de pedido que quedaron en ERP/ARCA sin Anita.
 */
final class PedidoFacturaAnitaRegrabacionService
{
    public function __construct(
        private readonly PedidoFacturaAnitaDeferEjecucionService $ejecucionService,
    ) {
    }

    /**
     * @param  array<string, mixed>|null  $anitaPendiente
     * @param  array<string, mixed>|null  $vencaePendiente
     */
    public function registrarPendiente(int $ventaId, ?array $anitaPendiente, ?array $vencaePendiente): void
    {
        if ($ventaId <= 0) {
            return;
        }

        $pedidoId = (int) ($anitaPendiente['pedido_id'] ?? 0);
        if ($pedidoId <= 0) {
            $pedidoId = (int) (Venta::query()->whereKey($ventaId)->value('pedido_id') ?? 0);
        }

        if (is_array($anitaPendiente) && empty($anitaPendiente['path_sistema'])) {
            $path = PedidoFacturaAnitaArchivosSupport::pathSistema($anitaPendiente);
            if ($path !== null) {
                $anitaPendiente['path_sistema'] = $path;
            }
        }

        VentaAnitaReplica::query()->updateOrCreate(
            ['venta_id' => $ventaId],
            [
                'pedido_id' => $pedidoId > 0 ? $pedidoId : null,
                'estado' => VentaAnitaReplica::ESTADO_PENDIENTE,
                'error_mensaje' => null,
                'payload_anita' => $anitaPendiente,
                'payload_vencae' => $vencaePendiente,
            ],
        );

        PedidoFacturaAnitaSemaforoSupport::levantar();
    }

    /**
     * @param  array<string, mixed>  $inspeccion
     */
    public function registrarInspeccion(int $ventaId, array $inspeccion): void
    {
        if ($ventaId <= 0) {
            return;
        }

        $fila = VentaAnitaReplica::query()->where('venta_id', $ventaId)->first();
        if ($fila === null) {
            return;
        }

        $fila->archivos_estado = [
            'vacio' => (bool) ($inspeccion['vacio'] ?? false),
            'completo' => (bool) ($inspeccion['completo'] ?? false),
            'presentes' => $inspeccion['presentes'] ?? [],
            'faltantes' => $inspeccion['faltantes'] ?? [],
            'error' => $inspeccion['error'] ?? null,
            'inspeccionado_at' => now()->toDateTimeString(),
        ];
        $fila->save();
    }

    public function marcarOk(int $ventaId): void
    {
        if ($ventaId <= 0) {
            return;
        }

        VentaAnitaReplica::query()->where('venta_id', $ventaId)->update([
            'estado' => VentaAnitaReplica::ESTADO_OK,
            'error_mensaje' => null,
            'synced_at' => now(),
            'ultimo_intento_at' => now(),
        ]);

        PedidoFacturaAnitaSemaforoSupport::sincronizarDesdeTabla();
    }

    public function marcarError(int $ventaId, string $mensaje): void
    {
        if ($ventaId <= 0) {
            return;
        }

        $fila = VentaAnitaReplica::query()->where('venta_id', $ventaId)->first();
        if ($fila === null) {
            $this->registrarPendiente($ventaId, null, null);
            $fila = VentaAnitaReplica::query()->where('venta_id', $ventaId)->first();
        }
        if ($fila === null) {
            return;
        }

        $fila->estado = VentaAnitaReplica::ESTADO_ERROR;
        $fila->error_mensaje = mb_substr($mensaje, 0, 2000);
        $fila->intentos = (int) $fila->intentos + 1;
        $fila->ultimo_intento_at = now();
        $fila->save();

        PedidoFacturaAnitaSemaforoSupport::levantar();

        Log::warning('pedido.anita.semaforo.levantado', [
            'venta_id' => $ventaId,
            'intentos' => $fila->intentos,
            'msg' => $mensaje,
        ]);
    }

    /**
     * @return array{semaforo: bool, abiertos: int, agotados: int, procesados: int, ok: int, error: int, omitidos: int, detalle: list<array<string, mixed>>}
     */
    public function procesarPendientes(bool $ejecutar, int $limite = 20): array
    {
        $maxIntentos = max(1, (int) config('facturacion.ANITA_PEDIDO_REGRABAR_MAX_INTENTOS', 20));
        $limite = max(1, $limite);

        $abiertosTotales = VentaAnitaReplica::query()
            ->whereIn('estado', [VentaAnitaReplica::ESTADO_PENDIENTE, VentaAnitaReplica::ESTADO_ERROR])
            ->count();
        $agotados = VentaAnitaReplica::query()
            ->whereIn('estado', [VentaAnitaReplica::ESTADO_PENDIENTE, VentaAnitaReplica::ESTADO_ERROR])
            ->where('intentos', '>=', $maxIntentos)
            ->count();

        $abiertos = VentaAnitaReplica::query()
            ->whereIn('estado', [VentaAnitaReplica::ESTADO_PENDIENTE, VentaAnitaReplica::ESTADO_ERROR])
            ->where('intentos', '<', $maxIntentos)
            ->orderBy('id')
            ->limit($limite)
            ->get();

        $resultado = [
            'semaforo' => PedidoFacturaAnitaSemaforoSupport::levantado(),
            'abiertos' => $abiertosTotales,
            'agotados' => $agotados,
            'procesados' => 0,
            'ok' => 0,
            'error' => 0,
            'omitidos' => 0,
            'detalle' => [],
        ];

        foreach ($abiertos as $fila) {
            $detalle = [
                'venta_id' => (int) $fila->venta_id,
                'pedido_id' => $fila->pedido_id,
                'estado' => $fila->estado,
                'intentos' => (int) $fila->intentos,
                'accion' => $ejecutar ? 'regrabar' : 'simulado',
                'mensaje' => '',
            ];

            if (! is_array($fila->payload_anita) && ! is_array($fila->payload_vencae)) {
                $detalle['accion'] = 'omitido';
                $detalle['mensaje'] = 'Sin payload para regrabar';
                $resultado['omitidos']++;
                $resultado['detalle'][] = $detalle;
                continue;
            }

            $inspeccion = PedidoFacturaAnitaArchivosSupport::inspeccionar(
                is_array($fila->payload_anita) ? $fila->payload_anita : null,
                is_array($fila->payload_vencae) ? $fila->payload_vencae : null,
            );
            $this->registrarInspeccion((int) $fila->venta_id, $inspeccion);
            $detalle['mensaje'] = $this->mensajeDesdeInspeccion($inspeccion);

            if ($inspeccion['ok'] && $inspeccion['completo']) {
                if ($ejecutar) {
                    $this->marcarOk((int) $fila->venta_id);
                    $detalle['estado'] = VentaAnitaReplica::ESTADO_OK;
                    $detalle['accion'] = 'ya_completo';
                    $resultado['ok']++;
                } else {
                    $detalle['accion'] = 'ya_completo';
                }
                $resultado['procesados']++;
                $resultado['detalle'][] = $detalle;
                continue;
            }

            if (! $ejecutar) {
                $resultado['procesados']++;
                $resultado['detalle'][] = $detalle;
                continue;
            }

            try {
                $this->ejecucionService->ejecutar(
                    (int) $fila->venta_id,
                    is_array($fila->payload_anita) ? $fila->payload_anita : null,
                    is_array($fila->payload_vencae) ? $fila->payload_vencae : null,
                );
                $fila->refresh();
                $detalle['estado'] = $fila->estado;
                if ($fila->estado === VentaAnitaReplica::ESTADO_OK) {
                    $resultado['ok']++;
                } else {
                    $resultado['error']++;
                    $detalle['mensaje'] = (string) $fila->error_mensaje;
                }
            } catch (\Throwable $e) {
                $this->marcarError((int) $fila->venta_id, $e->getMessage());
                $detalle['estado'] = VentaAnitaReplica::ESTADO_ERROR;
                $detalle['mensaje'] = $e->getMessage();
                $resultado['error']++;
            }

            $resultado['procesados']++;
            $resultado['detalle'][] = $detalle;
        }

        PedidoFacturaAnitaSemaforoSupport::sincronizarDesdeTabla();
        $resultado['semaforo'] = PedidoFacturaAnitaSemaforoSupport::levantado();

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $inspeccion
     */
    private function mensajeDesdeInspeccion(array $inspeccion): string
    {
        if (! ($inspeccion['ok'] ?? false)) {
            return (string) ($inspeccion['error'] ?? 'No se pudo leer Anita');
        }
        if ($inspeccion['completo'] ?? false) {
            return 'Anita completa';
        }
        if ($inspeccion['vacio'] ?? false) {
            return 'Anita vacía: '.implode(', ', $inspeccion['esperados'] ?? []);
        }

        $faltantes = $inspeccion['faltantes'] ?? [];

        return 'Faltan '.implode(', ', is_array($faltantes) ? $faltantes : []);
    }
}

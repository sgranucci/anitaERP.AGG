<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Contable\Asiento;
use App\Repositories\Contable\AsientoRepository;
use App\Support\Compras\ComprobanteProveedorAnitaBridgeGate;
use App\Support\Compras\ComprobanteProveedorAnitaSyncEstado;
use App\Support\Compras\ComprobanteProveedorEstados;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Crown: manda al bridge las facturas contabilizadas que todavía no están en Anita.
 * Lo dispara el cron de las 13 y las 16. En el resto de las instalaciones no corre.
 */
final class ComprobanteProveedorAnitaSyncBridgeService
{
    public function __construct(
        private readonly ComprobanteProveedorAnitaSyncService $anitaSync,
        private readonly ComprobanteProveedorAsientoService $asientoService,
        private readonly AsientoRepository $asientoRepository,
    ) {}

    /**
     * @return array{
     *     omitida: bool,
     *     motivo: ?string,
     *     dry_run: bool,
     *     total: int,
     *     sincronizadas: int,
     *     errores: list<array{id: int, etiqueta: string, mensaje: string}>,
     *     filas: list<array{id: int, etiqueta: string, estado: string}>
     * }
     */
    public function barrer(bool $dryRun = false, ?int $limite = null): array
    {
        $vacio = [
            'omitida' => false,
            'motivo' => null,
            'dry_run' => $dryRun,
            'total' => 0,
            'sincronizadas' => 0,
            'errores' => [],
            'filas' => [],
        ];

        if (! ComprobanteProveedorAnitaBridgeGate::syncBridgeProgramadoHabilitado()) {
            $vacio['omitida'] = true;
            $vacio['motivo'] = 'El barrido al bridge es solo de Crown, o la escritura Anita está apagada.';

            return $vacio;
        }

        $query = Comprobante_Proveedor::query()
            ->where('estado', ComprobanteProveedorEstados::CONTABILIZADO)
            ->whereNotNull('asiento_id')
            ->whereIn('anita_sync_estado', [
                ComprobanteProveedorAnitaSyncEstado::PENDIENTE,
                ComprobanteProveedorAnitaSyncEstado::ERROR,
            ])
            ->orderBy('id');

        if ($limite !== null && $limite > 0) {
            $query->limit($limite);
        }

        $ids = $query->pluck('id');
        $vacio['total'] = $ids->count();

        foreach ($ids as $id) {
            $cp = Comprobante_Proveedor::query()
                ->with(['proveedores', 'tipotransaccion_compras'])
                ->find((int) $id);
            if ($cp === null) {
                continue;
            }

            $etiqueta = $this->etiqueta($cp);
            if ($dryRun) {
                $vacio['filas'][] = [
                    'id' => (int) $cp->id,
                    'etiqueta' => $etiqueta,
                    'estado' => (string) ($cp->anita_sync_estado ?? ''),
                ];

                continue;
            }

            try {
                $this->sincronizarUno($cp);
                $vacio['sincronizadas']++;
                $vacio['filas'][] = [
                    'id' => (int) $cp->id,
                    'etiqueta' => $etiqueta,
                    'estado' => ComprobanteProveedorAnitaSyncEstado::SYNC_OK,
                ];
            } catch (Throwable $e) {
                $mensaje = mb_substr($e->getMessage(), 0, 2000);
                $cp->refresh();
                $cp->forceFill([
                    'anita_sync_estado' => ComprobanteProveedorAnitaSyncEstado::ERROR,
                    'anita_sync_error' => $mensaje,
                    'anita_sync_at' => now(),
                ])->save();
                $vacio['errores'][] = [
                    'id' => (int) $cp->id,
                    'etiqueta' => $etiqueta,
                    'mensaje' => $mensaje,
                ];
                Log::warning('comprobante_proveedor.sync_bridge_fallo', [
                    'comprobante_id' => (int) $cp->id,
                    'error' => $mensaje,
                ]);
            }
        }

        return $vacio;
    }

    private function sincronizarUno(Comprobante_Proveedor $cp): void
    {
        if ((int) ($cp->anita_nro_interno ?? 0) > 0) {
            $this->anitaSync->syncUpdate($cp);
        } else {
            $this->anitaSync->syncCreate($cp);
        }

        $cp->refresh();
        $asiento = Asiento::query()->with('asiento_movimientos')->find((int) $cp->asiento_id);
        if ($asiento === null) {
            throw new RuntimeException('La factura '.$cp->id.' no tiene asiento para ctamov.');
        }

        $payload = $this->asientoRepository->armarPayloadAnitaDesdeModelo($asiento);
        $this->asientoService->sincronizarCtamovAnita($cp, $payload);

        $cp->forceFill([
            'anita_sync_estado' => ComprobanteProveedorAnitaSyncEstado::SYNC_OK,
            'anita_sync_error' => null,
            'anita_sync_at' => now(),
        ])->save();
    }

    private function etiqueta(Comprobante_Proveedor $cp): string
    {
        $tipo = (string) ($cp->tipotransaccion_compras?->abreviatura ?? '???');
        $letra = strtoupper(substr((string) ($cp->letra ?? '?'), 0, 1));

        return sprintf('%s %s-%s-%s', $tipo, $letra, (int) $cp->sucursal, (int) $cp->numerocomprobante);
    }
}

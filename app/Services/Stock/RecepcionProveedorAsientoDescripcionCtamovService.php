<?php

namespace App\Services\Stock;

use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Stock\RecepcionProveedorAsientoDescripcionSupport;
use App\Support\Stock\RecepcionProveedorEstados;
use Illuminate\Support\Facades\Log;

/**
 * Backfill de leyendas en asiento ERP, asiento_movimiento y contab.ctamov Anita.
 */
class RecepcionProveedorAsientoDescripcionCtamovService
{
    public function __construct(
        private readonly RecepcionProveedorAsientoService $asientoService,
    ) {
    }

    /**
     * @param  array{
     *     id?: int|null,
     *     limite?: int|null,
     *     dry_run?: bool,
     *     incluir_importadas?: bool,
     *     solo_anita?: bool,
     * }  $opciones
     * @return array{
     *     candidatas: int,
     *     ya_ok: int,
     *     actualizadas_erp: int,
     *     actualizadas_anita: int,
     *     errores: int,
     *     detalle: list<array<string, mixed>>
     * }
     */
    public function ejecutar(array $opciones = []): array
    {
        $dryRun = (bool) ($opciones['dry_run'] ?? false);
        $soloAnita = (bool) ($opciones['solo_anita'] ?? false);
        $incluirImportadas = (bool) ($opciones['incluir_importadas'] ?? false);
        $limite = isset($opciones['limite']) ? (int) $opciones['limite'] : null;
        $idFiltro = isset($opciones['id']) ? (int) $opciones['id'] : null;

        $query = Recepcion_Proveedor::query()
            ->where('estado', RecepcionProveedorEstados::CONFIRMADA)
            ->where('asiento_id', '>', 0)
            ->orderBy('id');

        if (! $incluirImportadas) {
            $query->where('origen_carga', '!=', 'ANITA_IMPORT');
        }

        if ($idFiltro !== null && $idFiltro > 0) {
            $query->where('id', $idFiltro);
        }

        if ($limite !== null && $limite > 0) {
            $query->limit($limite);
        }

        $recepciones = $query->get();

        $stats = [
            'candidatas' => $recepciones->count(),
            'ya_ok' => 0,
            'actualizadas_erp' => 0,
            'actualizadas_anita' => 0,
            'errores' => 0,
            'detalle' => [],
        ];

        foreach ($recepciones as $recepcion) {
            try {
                $resultado = $this->procesarRecepcion($recepcion, $dryRun, $soloAnita);
                $stats['detalle'][] = $resultado;

                $estado = (string) ($resultado['estado'] ?? '');
                if ($estado === 'ya_ok') {
                    $stats['ya_ok']++;
                } elseif ($estado === 'actualizada_erp') {
                    $stats['actualizadas_erp']++;
                } elseif ($estado === 'actualizada_anita') {
                    $stats['actualizadas_anita']++;
                } elseif ($estado === 'actualizada_erp_anita') {
                    $stats['actualizadas_erp']++;
                    $stats['actualizadas_anita']++;
                }
            } catch (\Throwable $e) {
                $stats['errores']++;
                $stats['detalle'][] = [
                    'recepcion_id' => (int) $recepcion->id,
                    'numerorecepcion' => (int) $recepcion->numerorecepcion,
                    'asiento_id' => (int) ($recepcion->asiento_id ?? 0),
                    'estado' => 'error',
                    'mensaje' => $e->getMessage(),
                ];
                Log::error('RecepcionProveedorAsientoDescripcionCtamov: error', [
                    'recepcion_id' => $recepcion->id,
                    'exception' => $e,
                ]);
            }
        }

        return $stats;
    }

    public function contarCandidatas(bool $incluirImportadas = false, ?int $id = null): int
    {
        $query = Recepcion_Proveedor::query()
            ->where('estado', RecepcionProveedorEstados::CONFIRMADA)
            ->where('asiento_id', '>', 0);

        if (! $incluirImportadas) {
            $query->where('origen_carga', '!=', 'ANITA_IMPORT');
        }

        if ($id !== null && $id > 0) {
            $query->where('id', $id);
        }

        return (int) $query->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function procesarRecepcion(
        Recepcion_Proveedor $recepcion,
        bool $dryRun,
        bool $soloAnita,
    ): array {
        $recepcion->loadMissing(['proveedores', 'asientos.asiento_movimientos']);

        if (! $this->asientoService->debeGenerarAsiento((int) $recepcion->empresa_id)) {
            return [
                'recepcion_id' => (int) $recepcion->id,
                'numerorecepcion' => (int) $recepcion->numerorecepcion,
                'asiento_id' => (int) ($recepcion->asiento_id ?? 0),
                'estado' => 'omitida',
                'mensaje' => 'Contabilidad de recepción inactiva para la empresa.',
            ];
        }

        if ($this->asientoService->recepcionSinImporteContable($recepcion)) {
            return [
                'recepcion_id' => (int) $recepcion->id,
                'numerorecepcion' => (int) $recepcion->numerorecepcion,
                'asiento_id' => (int) ($recepcion->asiento_id ?? 0),
                'estado' => 'omitida',
                'mensaje' => 'Recepción sin importe contable: no requiere asiento COM.',
            ];
        }

        $asientoId = (int) ($recepcion->asiento_id ?? 0);
        $descripcionErp = RecepcionProveedorAsientoDescripcionSupport::descripcionAsientoErp($recepcion);
        $descripcionCtamov = RecepcionProveedorAsientoDescripcionSupport::descripcionCtamovAnita($recepcion);

        $asiento = $recepcion->asientos;
        $necesitaErp = false;
        $necesitaAnita = true;

        if (! $soloAnita && $asiento) {
            $necesitaErp = trim((string) ($asiento->observacion ?? '')) !== $descripcionErp;
            if (! $necesitaErp) {
                foreach ($asiento->asiento_movimientos as $mov) {
                    if (trim((string) ($mov->observacion ?? '')) !== $descripcionCtamov) {
                        $necesitaErp = true;
                        break;
                    }
                }
            }
        }

        $base = [
            'recepcion_id' => (int) $recepcion->id,
            'numerorecepcion' => (int) $recepcion->numerorecepcion,
            'asiento_id' => $asientoId,
            'descripcion_erp' => $descripcionErp,
            'descripcion_ctamov' => $descripcionCtamov,
            'requiere_erp' => $necesitaErp,
            'requiere_anita' => $necesitaAnita,
        ];

        if (! $necesitaErp && ! $necesitaAnita) {
            return array_merge($base, ['estado' => 'ya_ok']);
        }

        if ($dryRun) {
            return array_merge($base, ['estado' => 'pendiente']);
        }

        if ($necesitaErp && ! $soloAnita) {
            $this->actualizarDescripcionesErp($asientoId, $descripcionErp, $descripcionCtamov);
        }

        $preview = $this->asientoService->previewAsientoContable($recepcion);
        $this->asientoService->sincronizarCtamovAnitaRecepcion($recepcion, $preview);

        if ($necesitaErp && ! $soloAnita) {
            return array_merge($base, ['estado' => 'actualizada_erp_anita']);
        }

        return array_merge($base, ['estado' => 'actualizada_anita']);
    }

    private function actualizarDescripcionesErp(int $asientoId, string $descripcionErp, string $descripcionCtamov): void
    {
        Asiento::query()
            ->whereKey($asientoId)
            ->update(['observacion' => $descripcionErp]);

        Asiento_Movimiento::query()
            ->where('asiento_id', $asientoId)
            ->update(['observacion' => $descripcionCtamov]);
    }
}

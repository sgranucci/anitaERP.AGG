<?php

namespace App\Services\Stock;

use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Stock\RecepcionProveedorAsientoAnitaCtamovSupport;
use App\Support\Stock\RecepcionProveedorCuadreContableSupport;
use App\Support\Stock\RecepcionProveedorEstados;
use Illuminate\Support\Facades\Log;

/**
 * Recuadra asientos de recepciones ERP (Σ cant×precio de línea, sin descuento de pie de OC)
 * y sincroniza contab.ctamov en Anita.
 */
class RecepcionProveedorAsientoRecuadreService
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
     *     ya_cuadradas: int,
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
        $tol = RecepcionProveedorCuadreContableSupport::tolerancia();

        $stats = [
            'candidatas' => $recepciones->count(),
            'ya_cuadradas' => 0,
            'actualizadas_erp' => 0,
            'actualizadas_anita' => 0,
            'errores' => 0,
            'detalle' => [],
        ];

        foreach ($recepciones as $recepcion) {
            try {
                $resultado = $this->procesarRecepcion($recepcion, $dryRun, $tol, $soloAnita);
                $stats['detalle'][] = $resultado;

                $estado = (string) ($resultado['estado'] ?? '');
                if ($estado === 'ya_cuadrada') {
                    $stats['ya_cuadradas']++;
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
                Log::error('RecepcionProveedorAsientoRecuadre: error', [
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
        float $tol,
        bool $soloAnita,
    ): array {
        $recepcion->loadMissing([
            'recepcion_proveedor_articulos.articulos',
            'ordencompras',
            'asientos.asiento_movimientos',
        ]);

        if (! $this->asientoService->debeGenerarAsiento((int) $recepcion->empresa_id)) {
            return [
                'recepcion_id' => (int) $recepcion->id,
                'numerorecepcion' => (int) $recepcion->numerorecepcion,
                'asiento_id' => (int) ($recepcion->asiento_id ?? 0),
                'estado' => 'omitida',
                'mensaje' => 'Contabilidad de recepción inactiva para la empresa.',
            ];
        }

        $asientoId = (int) ($recepcion->asiento_id ?? 0);
        $preview = $this->asientoService->previewAsientoContable($recepcion);
        $debeNuevo = round((float) ($preview['total_debe'] ?? 0), 2);

        $movimientos = $recepcion->asientos?->asiento_movimientos ?? collect();
        $totalesErp = RecepcionProveedorCuadreContableSupport::totalesDesdeMovimientos($movimientos);
        $debeErp = round((float) ($totalesErp['debe'] ?? 0), 2);
        $diffErp = round($debeNuevo - $debeErp, 2);

        $totalesAnita = RecepcionProveedorAsientoAnitaCtamovSupport::totalesCtamovRecepcion($recepcion);
        $debeAnita = $totalesAnita !== null ? round((float) $totalesAnita['debe'], 2) : null;
        $diffAnita = $debeAnita !== null ? round($debeNuevo - $debeAnita, 2) : null;

        $necesitaErp = ! $soloAnita && abs($diffErp) >= $tol;
        $necesitaAnita = $debeAnita === null || abs((float) $diffAnita) >= $tol;

        $base = [
            'recepcion_id' => (int) $recepcion->id,
            'numerorecepcion' => (int) $recepcion->numerorecepcion,
            'asiento_id' => $asientoId,
            'debe_esperado' => $debeNuevo,
            'debe_erp' => $debeErp,
            'debe_anita' => $debeAnita,
            'diff_erp' => $diffErp,
            'diff_anita' => $diffAnita,
        ];

        if (! $necesitaErp && ! $necesitaAnita) {
            return array_merge($base, ['estado' => 'ya_cuadrada']);
        }

        if ($dryRun) {
            return array_merge($base, [
                'estado' => 'pendiente',
                'requiere_erp' => $necesitaErp,
                'requiere_anita' => $necesitaAnita,
            ]);
        }

        if ($necesitaErp) {
            $this->asientoService->recuadrarAsientoExistente($recepcion);

            return array_merge($base, ['estado' => $necesitaAnita ? 'actualizada_erp_anita' : 'actualizada_erp']);
        }

        if ($necesitaAnita) {
            $this->asientoService->sincronizarCtamovAnitaRecepcion($recepcion, $preview);

            return array_merge($base, ['estado' => 'actualizada_anita']);
        }

        return array_merge($base, ['estado' => 'ya_cuadrada']);
    }
}

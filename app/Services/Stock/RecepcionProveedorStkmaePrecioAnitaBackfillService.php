<?php

namespace App\Services\Stock;

use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Stock\RecepcionProveedorEstados;
use App\Support\Stock\StkmaePrecioCompraAnitaBridgeSupport;
use Illuminate\Support\Carbon;

class RecepcionProveedorStkmaePrecioAnitaBackfillService
{
    /**
     * @param  array{
     *     desde?: string|null,
     *     hasta?: string|null,
     *     id?: int|null,
     *     dry_run?: bool,
     *     reprocesar?: bool,
     *     incluir_importadas?: bool,
     *     limite?: int|null,
     * }  $opciones
     * @return array{
     *     candidatas: int,
     *     procesadas: int,
     *     articulos_stkmae: int,
     *     sin_lineas: int,
     *     errores: int,
     *     omitidas_sync: int,
     * }
     */
    public function ejecutar(array $opciones, ?callable $onProgreso = null): array
    {
        $dryRun = (bool) ($opciones['dry_run'] ?? false);
        $reprocesar = (bool) ($opciones['reprocesar'] ?? false);
        $incluirImportadas = (bool) ($opciones['incluir_importadas'] ?? false);
        $limite = isset($opciones['limite']) ? (int) $opciones['limite'] : null;

        $query = $this->queryCandidatas($opciones, $reprocesar, $incluirImportadas);
        $candidatas = (int) (clone $query)->count();

        if ($limite !== null && $limite > 0) {
            $query->limit($limite);
        }

        $stats = [
            'candidatas' => $candidatas,
            'procesadas' => 0,
            'articulos_stkmae' => 0,
            'sin_lineas' => 0,
            'errores' => 0,
        ];

        foreach ($query->cursor() as $recepcion) {
            /** @var Recepcion_Proveedor $recepcion */
            try {
                $grupos = StkmaePrecioCompraAnitaBridgeSupport::agruparLineasRecepcion($recepcion);
                if ($grupos === []) {
                    $stats['sin_lineas']++;
                    if (! $dryRun) {
                        $this->marcarSincronizada($recepcion);
                    }
                    $stats['procesadas']++;
                    if ($onProgreso) {
                        $onProgreso($recepcion, 0, null);
                    }

                    continue;
                }

                if ($dryRun) {
                    $stats['procesadas']++;
                    $stats['articulos_stkmae'] += count($grupos);
                    if ($onProgreso) {
                        $onProgreso($recepcion, count($grupos), null);
                    }

                    continue;
                }

                $actualizados = StkmaePrecioCompraAnitaBridgeSupport::actualizarDesdeRecepcion($recepcion);
                $this->marcarSincronizada($recepcion);
                $stats['procesadas']++;
                $stats['articulos_stkmae'] += $actualizados;
                if ($onProgreso) {
                    $onProgreso($recepcion, $actualizados, null);
                }
            } catch (\Throwable $e) {
                $stats['errores']++;
                if ($onProgreso) {
                    $onProgreso($recepcion, 0, $e);
                }
            }
        }

        return $stats;
    }

    /**
     * @param  array{desde?: string|null, hasta?: string|null, id?: int|null}  $opciones
     */
    public function contarCandidatas(array $opciones, bool $reprocesar = false, bool $incluirImportadas = false): int
    {
        return (int) $this->queryCandidatas($opciones, $reprocesar, $incluirImportadas)->count();
    }

    /**
     * @param  array{desde?: string|null, hasta?: string|null, id?: int|null}  $opciones
     */
    private function queryCandidatas(array $opciones, bool $reprocesar, bool $incluirImportadas)
    {
        $query = Recepcion_Proveedor::query()
            ->where('estado', RecepcionProveedorEstados::CONFIRMADA)
            ->where('tipo', Recepcion_Proveedor::TIPO_RECEPCION)
            ->where('numerorecepcion', '>', 0)
            ->orderBy('fecha')
            ->orderBy('id');

        if (! $reprocesar) {
            $query->whereNull('stkmae_precio_anita_sync_at');
        }

        if (! $incluirImportadas) {
            $query->where('origen_carga', '!=', 'ANITA_IMPORT');
        }

        if (! empty($opciones['id'])) {
            $query->where('id', (int) $opciones['id']);
        }

        if (! empty($opciones['desde'])) {
            $query->whereDate('fecha', '>=', Carbon::parse((string) $opciones['desde'])->toDateString());
        }

        if (! empty($opciones['hasta'])) {
            $query->whereDate('fecha', '<=', Carbon::parse((string) $opciones['hasta'])->toDateString());
        }

        return $query;
    }

    private function marcarSincronizada(Recepcion_Proveedor $recepcion): void
    {
        $recepcion->forceFill(['stkmae_precio_anita_sync_at' => now()])->save();
    }
}

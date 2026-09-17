<?php

namespace App\Services\Stock;

use App\Models\Compras\Ordencompra;
use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Stock\RecepcionProveedorEstados;
use App\Support\Stock\RecepcionProveedorOcPendienteSupport;
use App\Support\Stock\RecepcionProveedorRedistribuirAtribucionOcSupport;
use Illuminate\Support\Facades\DB;

/**
 * Repara atribución COM→línea OC apilada en un solo ordencompra_articulo_id.
 * No toca stock/movimientos ni Anita; solo recepcion_proveedor_articulo.
 */
class RecepcionProveedorRedistribuirAtribucionOcService
{
    /**
     * @param  list<int>  $numerosOc
     * @return array{
     *   ocs: list<array<string, mixed>>,
     *   total_cambios: int,
     *   errores: int
     * }
     */
    public function ejecutar(array $numerosOc, bool $dryRun = true, ?callable $onError = null): array
    {
        $resultado = [
            'ocs' => [],
            'total_cambios' => 0,
            'errores' => 0,
        ];

        foreach ($numerosOc as $numeroOc) {
            $numeroOc = (int) $numeroOc;
            if ($numeroOc <= 0) {
                continue;
            }

            try {
                $detalle = $this->procesarOc($numeroOc, $dryRun);
                $resultado['ocs'][] = $detalle;
                $resultado['total_cambios'] += (int) ($detalle['cambios_aplicados'] ?? 0);
            } catch (\Throwable $e) {
                $resultado['errores']++;
                $resultado['ocs'][] = [
                    'numeroordencompra' => $numeroOc,
                    'error' => $e->getMessage(),
                ];
                if ($onError !== null) {
                    $onError($numeroOc, $e);
                }
            }
        }

        return $resultado;
    }

    /**
     * @return array<string, mixed>
     */
    private function procesarOc(int $numeroOc, bool $dryRun): array
    {
        $oc = Ordencompra::query()
            ->where('numeroordencompra', $numeroOc)
            ->first();

        if ($oc === null) {
            throw new \RuntimeException("OC {$numeroOc} no encontrada en ERP.");
        }

        $lineasOc = DB::table('ordencompra_articulo')
            ->where('ordencompra_id', $oc->id)
            ->orderBy('id')
            ->get(['id', 'articulo_id', 'penvp_orden', 'penvp_nro_interno', 'cantidad'])
            ->map(static fn ($row): array => [
                'id' => (int) $row->id,
                'articulo_id' => (int) $row->articulo_id,
                'penvp_orden' => (int) ($row->penvp_orden ?? 0),
                'penvp_nro_interno' => (int) ($row->penvp_nro_interno ?? 0),
                'cantidad' => (float) $row->cantidad,
            ])
            ->all();

        $recepciones = Recepcion_Proveedor::query()
            ->where('ordencompra_id', $oc->id)
            ->where('estado', RecepcionProveedorEstados::CONFIRMADA)
            ->where('tipo', Recepcion_Proveedor::TIPO_RECEPCION)
            ->orderBy('fecha')
            ->orderBy('id')
            ->with(['recepcion_proveedor_articulos' => static function ($q) {
                $q->orderBy('id');
            }])
            ->get();

        $coms = [];
        foreach ($recepciones as $rp) {
            $lineas = [];
            foreach ($rp->recepcion_proveedor_articulos as $rpa) {
                $lineas[] = [
                    'rpa_id' => (int) $rpa->id,
                    'ordencompra_articulo_id' => $rpa->ordencompra_articulo_id !== null
                        ? (int) $rpa->ordencompra_articulo_id
                        : null,
                    'articulo_id' => (int) ($rpa->articulo_id ?? 0),
                    'cantidad' => (float) $rpa->cantidad,
                    'penvp_orden' => $rpa->penvp_orden !== null ? (int) $rpa->penvp_orden : null,
                    'penvp_nro_interno' => $rpa->penvp_nro_interno !== null ? (int) $rpa->penvp_nro_interno : null,
                ];
            }
            $coms[] = [
                'recepcion_id' => (int) $rp->id,
                'numerorecepcion' => $rp->numerorecepcion,
                'fecha' => $rp->fecha ? (string) $rp->fecha : null,
                'lineas' => $lineas,
            ];
        }

        $plan = RecepcionProveedorRedistribuirAtribucionOcSupport::planificar($lineasOc, $coms);
        $pendienteAntes = $this->resumenPendiente($oc->id, $lineasOc);

        $aplicados = 0;
        if (! $dryRun && $plan['cambios'] !== []) {
            DB::transaction(function () use ($plan, &$aplicados) {
                foreach ($plan['cambios'] as $cambio) {
                    $updated = DB::table('recepcion_proveedor_articulo')
                        ->where('id', $cambio['rpa_id'])
                        ->where('ordencompra_articulo_id', $cambio['desde_oc_art'])
                        ->update([
                            'ordencompra_articulo_id' => $cambio['hacia_oc_art'],
                            'penvp_orden' => $cambio['penvp_orden'] > 0 ? $cambio['penvp_orden'] : null,
                            'penvp_nro_interno' => $cambio['penvp_nro_interno'] > 0
                                ? $cambio['penvp_nro_interno']
                                : null,
                            'updated_at' => now(),
                        ]);
                    $aplicados += (int) $updated;
                }
            });
        } elseif ($dryRun) {
            $aplicados = count($plan['cambios']);
        }

        $pendienteDespues = $dryRun
            ? $this->simularPendienteDespues($pendienteAntes, $plan['cambios'], $lineasOc)
            : $this->resumenPendiente($oc->id, $lineasOc);

        return [
            'numeroordencompra' => $numeroOc,
            'ordencompra_id' => (int) $oc->id,
            'lineas_oc' => count($lineasOc),
            'coms_revisados' => $plan['coms_revisados'],
            'coms_redistribuidos' => $plan['coms_redistribuidos'],
            'lineas_sin_atribuir' => $plan['lineas_sin_atribuir'],
            'cambios_planificados' => count($plan['cambios']),
            'cambios_aplicados' => $aplicados,
            'dry_run' => $dryRun,
            'internos_duplicados' => $plan['internos_duplicados'],
            'cambios' => $plan['cambios'],
            'pendiente_antes' => $pendienteAntes,
            'pendiente_despues' => $pendienteDespues,
            'lineas_visibles_antes' => count(array_filter(
                $pendienteAntes,
                static fn (array $r): bool => $r['pendiente'] > 0.000001
            )),
            'lineas_visibles_despues' => count(array_filter(
                $pendienteDespues,
                static fn (array $r): bool => $r['pendiente'] > 0.000001
            )),
        ];
    }

    /**
     * @param  list<array{id:int, cantidad:float}>  $lineasOc
     * @return list<array{ordencompra_articulo_id:int, pedida:float, recibida:float, pendiente:float}>
     */
    private function resumenPendiente(int $ordencompraId, array $lineasOc): array
    {
        $recibidos = RecepcionProveedorOcPendienteSupport::cantidadesRecibidasPorLineaOc($ordencompraId);
        $out = [];
        foreach ($lineasOc as $linea) {
            $id = (int) $linea['id'];
            $pedida = (float) $linea['cantidad'];
            $recibida = (float) ($recibidos[$id] ?? 0);
            $out[] = [
                'ordencompra_articulo_id' => $id,
                'pedida' => $pedida,
                'recibida' => $recibida,
                'pendiente' => RecepcionProveedorOcPendienteSupport::saldoPendienteLineaEstricto($pedida, $recibida),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{ordencompra_articulo_id:int, pedida:float, recibida:float, pendiente:float}>  $antes
     * @param  list<array{desde_oc_art:int, hacia_oc_art:int}>  $cambios
     * @param  list<array{id:int, cantidad:float}>  $lineasOc
     * @return list<array{ordencompra_articulo_id:int, pedida:float, recibida:float, pendiente:float}>
     */
    private function simularPendienteDespues(array $antes, array $cambios, array $lineasOc): array
    {
        $recibida = [];
        $pedida = [];
        foreach ($antes as $row) {
            $id = (int) $row['ordencompra_articulo_id'];
            $recibida[$id] = (float) $row['recibida'];
            $pedida[$id] = (float) $row['pedida'];
        }
        foreach ($lineasOc as $linea) {
            $pedida[(int) $linea['id']] = (float) $linea['cantidad'];
            $recibida[(int) $linea['id']] = $recibida[(int) $linea['id']] ?? 0.0;
        }

        foreach ($cambios as $cambio) {
            $desde = (int) $cambio['desde_oc_art'];
            $hacia = (int) $cambio['hacia_oc_art'];
            $recibida[$desde] = ($recibida[$desde] ?? 0) - 1.0;
            $recibida[$hacia] = ($recibida[$hacia] ?? 0) + 1.0;
        }

        $out = [];
        foreach ($lineasOc as $linea) {
            $id = (int) $linea['id'];
            $ped = (float) ($pedida[$id] ?? $linea['cantidad']);
            $rec = (float) ($recibida[$id] ?? 0);
            $out[] = [
                'ordencompra_articulo_id' => $id,
                'pedida' => $ped,
                'recibida' => $rec,
                'pendiente' => RecepcionProveedorOcPendienteSupport::saldoPendienteLineaEstricto($ped, $rec),
            ];
        }

        return $out;
    }
}

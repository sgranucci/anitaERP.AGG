<?php

namespace App\Services\Stock;

use App\ApiAnita;
use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Stock\RecepcionProveedorAnitaImportSupport;
use App\Support\Stock\RecepcionProveedorDepositoAnitaSupport;
use App\Support\Stock\RecepcionProveedorEstados;
use App\Support\Stock\Surmar\RecepcionProveedorSurmarAnitaBridgeSupport;
use App\Support\Stock\SurmarSupport;
use Illuminate\Support\Facades\DB;

/**
 * Corrige deposito_id en líneas COM importadas (ANITA_IMPORT).
 *
 * Solo actualiza recepcion_proveedor_articulo.deposito_id.
 * No modifica articulo_movimiento ni saldos de stock.
 */
class RecepcionProveedorCorregirDepositoAnitaImportService
{
    /**
     * @return array{candidatas: int, lineas_revisadas: int, lineas_actualizadas: int, sin_deposito_anita: int, sin_mapeo_erp: int, errores: int}
     */
    public function ejecutar(
        bool $dryRun = false,
        ?int $recepcionId = null,
        ?int $articuloId = null,
        ?callable $onError = null,
    ): array {
        RecepcionProveedorDepositoAnitaSupport::reiniciarCache();

        $stats = [
            'candidatas' => 0,
            'lineas_revisadas' => 0,
            'lineas_actualizadas' => 0,
            'sin_deposito_anita' => 0,
            'sin_mapeo_erp' => 0,
            'errores' => 0,
        ];

        $query = Recepcion_Proveedor::query()
            ->where('origen_carga', 'ANITA_IMPORT')
            ->where('estado', RecepcionProveedorEstados::CONFIRMADA)
            ->where('tipo', Recepcion_Proveedor::TIPO_RECEPCION)
            ->orderBy('id');

        if ($recepcionId !== null && $recepcionId > 0) {
            $query->whereKey($recepcionId);
        }

        if ($articuloId !== null && $articuloId > 0) {
            $query->whereHas('recepcion_proveedor_articulos', static function ($q) use ($articuloId) {
                $q->where('articulo_id', $articuloId);
            });
        }

        $stats['candidatas'] = (clone $query)->count();

        $query->with([
            'recepcion_proveedor_articulos' => static function ($q) use ($articuloId) {
                if ($articuloId !== null && $articuloId > 0) {
                    $q->where('articulo_id', $articuloId);
                }
                $q->with('articulos:id,sku');
            },
        ])->chunkById(50, function ($recepciones) use (
            $dryRun,
            $articuloId,
            $onError,
            &$stats
        ) {
            foreach ($recepciones as $recepcion) {
                try {
                    $this->corregirRecepcion($recepcion, $dryRun, $articuloId, $stats);
                } catch (\Throwable $e) {
                    $stats['errores']++;
                    if ($onError !== null) {
                        $onError($recepcion, $e);
                    }
                }
            }
        });

        return $stats;
    }

    /**
     * @param  array{candidatas: int, lineas_revisadas: int, lineas_actualizadas: int, sin_deposito_anita: int, sin_mapeo_erp: int, errores: int}  $stats
     */
    private function corregirRecepcion(
        Recepcion_Proveedor $recepcion,
        bool $dryRun,
        ?int $articuloIdFiltro,
        array &$stats,
    ): void {
        $tipo = (string) ($recepcion->anita_tipo ?? 'COM');
        $letra = (string) ($recepcion->anita_letra ?? 'X');
        $sucursal = (int) ($recepcion->anita_sucursal ?? 0);
        $nro = (int) ($recepcion->anita_nro ?? $recepcion->numerorecepcion ?? 0);
        $empresaId = (int) ($recepcion->empresa_id ?? 0);

        if ($sucursal <= 0 || $nro <= 0 || $empresaId <= 0) {
            throw new \RuntimeException('Recepción sin clave Anita o empresa.');
        }

        $lineasAnita = $this->listarRecepmov($tipo, $letra, $sucursal, $nro, $empresaId);
        if ($lineasAnita === []) {
            return;
        }

        $actualizaciones = [];
        $depositoCabecera = null;
        foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
            if ($articuloIdFiltro !== null && (int) $linea->articulo_id !== $articuloIdFiltro) {
                continue;
            }

            $stats['lineas_revisadas']++;

            $sku = trim((string) ($linea->articulos->sku ?? ''));
            if ($sku === '') {
                continue;
            }

            $codigoAnita = RecepcionProveedorDepositoAnitaSupport::codigoDepositoAnitaParaSku($lineasAnita, $sku);
            if ($codigoAnita === null) {
                $stats['sin_deposito_anita']++;

                continue;
            }

            $depositoId = RecepcionProveedorDepositoAnitaSupport::resolverIdDesdeCodigoAnita($codigoAnita, $empresaId);
            if ($depositoId === null) {
                $stats['sin_mapeo_erp']++;

                continue;
            }

            if ($depositoCabecera === null) {
                $depositoCabecera = $depositoId;
            }

            if ((int) $linea->deposito_id === $depositoId) {
                continue;
            }

            $actualizaciones[] = [
                'linea_id' => (int) $linea->id,
                'deposito_id' => $depositoId,
                'sku' => $sku,
                'codigo_anita' => $codigoAnita,
                'deposito_anterior' => (int) ($linea->deposito_id ?? 0),
            ];
        }

        $actualizarCabecera = $depositoCabecera !== null
            && (int) ($recepcion->deposito_id ?? 0) !== $depositoCabecera;

        if ($actualizaciones === [] && ! $actualizarCabecera) {
            return;
        }

        if ($dryRun) {
            $stats['lineas_actualizadas'] += count($actualizaciones);

            return;
        }

        DB::transaction(function () use ($actualizaciones, $actualizarCabecera, $depositoCabecera, $recepcion, &$stats) {
            foreach ($actualizaciones as $row) {
                DB::table('recepcion_proveedor_articulo')
                    ->where('id', $row['linea_id'])
                    ->update([
                        'deposito_id' => $row['deposito_id'],
                        'updated_at' => now(),
                    ]);
                $stats['lineas_actualizadas']++;
            }

            if ($actualizarCabecera && $depositoCabecera !== null) {
                $recepcion->update(['deposito_id' => $depositoCabecera]);
            }
        });
    }

    /**
     * @return list<object>
     */
    private function listarRecepmov(string $tipo, string $letra, int $sucursal, int $nro, int $empresaId): array
    {
        if (SurmarSupport::esEmpresaSurmar($empresaId)) {
            $api = new ApiAnita;
            $where = " WHERE recv_tipo = '".addslashes($tipo)."'"
                ." AND recv_letra = '".addslashes($letra)."'"
                .' AND recv_sucursal = '.(int) $sucursal
                .' AND recv_nro = '.(int) $nro;

            return ApiAnita::decodificarListaFilas($api->apiCall(
                RecepcionProveedorSurmarAnitaBridgeSupport::mergePayload([
                    'acc' => 'list',
                    'tabla' => 'recepmov',
                    'campos' => RecepcionProveedorSurmarAnitaBridgeSupport::camposLinea(),
                    'whereArmado' => $where,
                ])
            ));
        }

        return RecepcionProveedorAnitaImportSupport::listarRecepmov($tipo, $letra, $sucursal, $nro);
    }
}

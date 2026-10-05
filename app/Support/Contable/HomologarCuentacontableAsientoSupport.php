<?php

namespace App\Support\Contable;

use App\Models\Contable\Asiento_Movimiento;
use Illuminate\Support\Facades\DB;

/**
 * Reasigna asiento_movimiento.cuentacontable_id al plan de la empresa del asiento
 * cuando el código existe ahí y el id grabado es de otra empresa.
 * El observer de saldos mensuales mueve el snapshot al guardar.
 */
final class HomologarCuentacontableAsientoSupport
{
    /**
     * @return array{lineas: int, actualizadas: int, errores: list<string>}
     */
    public function ejecutar(bool $dryRun = false): array
    {
        $filas = DB::select('
            SELECT am.id AS movimiento_id, a.empresa_id, c.codigo
            FROM asiento a
            INNER JOIN asiento_movimiento am ON am.asiento_id = a.id
            INNER JOIN cuentacontable c ON c.id = am.cuentacontable_id
            WHERE c.empresa_id <> a.empresa_id
        ');

        $destinoPorCodigo = [];
        $cuentas = DB::table('cuentacontable')
            ->where('tipocuenta', 1)
            ->orderBy('id')
            ->get(['id', 'empresa_id', 'codigo']);
        foreach ($cuentas as $cuenta) {
            $key = ((int) $cuenta->empresa_id).'|'.trim((string) $cuenta->codigo);
            if (! isset($destinoPorCodigo[$key])) {
                $destinoPorCodigo[$key] = (int) $cuenta->id;
            }
        }

        $pares = [];
        foreach ($filas as $fila) {
            $key = ((int) $fila->empresa_id).'|'.trim((string) $fila->codigo);
            $destino = $destinoPorCodigo[$key] ?? 0;
            if ($destino <= 0) {
                continue;
            }
            $pares[] = (object) [
                'movimiento_id' => (int) $fila->movimiento_id,
                'destino_id' => $destino,
            ];
        }

        $resultado = [
            'lineas' => count($pares),
            'actualizadas' => 0,
            'errores' => [],
        ];

        if ($dryRun || $pares === []) {
            return $resultado;
        }

        foreach (array_chunk($pares, 200) as $lote) {
            $ids = [];
            $destinoPorId = [];
            foreach ($lote as $par) {
                $id = (int) $par->movimiento_id;
                $ids[] = $id;
                $destinoPorId[$id] = (int) $par->destino_id;
            }

            $movimientos = Asiento_Movimiento::query()->whereIn('id', $ids)->get();
            foreach ($movimientos as $movimiento) {
                $destino = $destinoPorId[(int) $movimiento->id] ?? 0;
                if ($destino <= 0 || (int) $movimiento->cuentacontable_id === $destino) {
                    continue;
                }

                try {
                    $movimiento->cuentacontable_id = $destino;
                    $movimiento->save();
                    $resultado['actualizadas']++;
                } catch (\Throwable $e) {
                    $resultado['errores'][] = 'Movimiento '.$movimiento->id.': '.$e->getMessage();
                }
            }
        }

        return $resultado;
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Caja_Movimiento_Cuentacaja;
use App\Models\Caja\Cuentacaja;
use Illuminate\Support\Facades\Log;

/**
 * Si el asiento de un ingreso/egreso cambia la cuenta contable del banco
 * (Gerli → Bibank) y la línea de caja sigue en el banco anterior, tesorería
 * (tesmov / pendientes de conciliar) y el emisor del mayor quedan en el banco viejo.
 *
 * Acá se reasigna la cuenta de caja al banco dueño de la cuenta contable nueva,
 * para que la reescritura de tesmov y el emisor sigan al mayor.
 */
final class IngresoEgresoBancoAsientoSupport
{
    /**
     * @param  list<array{linea_id: int, cuentacaja_id: int, codigo: string, codigo_contable: string, monto: float}>  $lineasCaja
     * @param  list<array{codigo_contable: string, monto: float}>  $lineasAsiento
     * @param  list<array{id: int, codigo: string, codigo_contable: string}>  $cuentasEmpresa
     * @return list<array{linea_id: int, cuentacaja_id_anterior: int, cuentacaja_id_nuevo: int, codigo_anterior: string, codigo_nuevo: string}>
     */
    public static function resolverReasignaciones(array $lineasCaja, array $lineasAsiento, array $cuentasEmpresa): array
    {
        $porCodigo = [];
        foreach ($cuentasEmpresa as $cuenta) {
            $codigoContable = self::normalizarCodigoContable((string) ($cuenta['codigo_contable'] ?? ''));
            $id = (int) ($cuenta['id'] ?? 0);
            if ($codigoContable === '' || $id <= 0) {
                continue;
            }
            $porCodigo[$codigoContable][] = [
                'id' => $id,
                'codigo' => trim((string) ($cuenta['codigo'] ?? '')),
            ];
        }

        $importePorCodigo = [];
        foreach ($lineasAsiento as $linea) {
            $codigoContable = self::normalizarCodigoContable((string) ($linea['codigo_contable'] ?? ''));
            if ($codigoContable === '') {
                continue;
            }
            $importePorCodigo[$codigoContable] = ($importePorCodigo[$codigoContable] ?? 0.0)
                + (float) ($linea['monto'] ?? 0);
        }

        $usados = [];
        $cambios = [];
        foreach ($lineasCaja as $linea) {
            $lineaId = (int) ($linea['linea_id'] ?? 0);
            $actualId = (int) ($linea['cuentacaja_id'] ?? 0);
            $codigoActual = self::normalizarCodigoContable((string) ($linea['codigo_contable'] ?? ''));
            $monto = (float) ($linea['monto'] ?? 0);
            if ($lineaId <= 0 || $actualId <= 0 || $codigoActual === '' || abs($monto) < 0.01) {
                continue;
            }
            // La cuenta del banco sigue en el asiento: no hubo cambio de banco.
            if (array_key_exists($codigoActual, $importePorCodigo)) {
                continue;
            }

            $destino = null;
            foreach ($importePorCodigo as $codigoContable => $importe) {
                if ($codigoContable === $codigoActual || ! self::mismoImporte((float) $importe, $monto)) {
                    continue;
                }
                $candidatos = $porCodigo[$codigoContable] ?? [];
                if (count($candidatos) !== 1) {
                    continue;
                }
                $candidato = $candidatos[0];
                if ((int) $candidato['id'] === $actualId || isset($usados[(int) $candidato['id']])) {
                    continue;
                }
                if ($destino !== null) {
                    $destino = null;
                    break;
                }
                $destino = $candidato;
            }
            if ($destino === null) {
                continue;
            }

            $usados[(int) $destino['id']] = true;
            $cambios[] = [
                'linea_id' => $lineaId,
                'cuentacaja_id_anterior' => $actualId,
                'cuentacaja_id_nuevo' => (int) $destino['id'],
                'codigo_anterior' => trim((string) ($linea['codigo'] ?? '')),
                'codigo_nuevo' => (string) $destino['codigo'],
            ];
        }

        return $cambios;
    }

    /**
     * @return list<array{linea_id: int, cuentacaja_id_anterior: int, cuentacaja_id_nuevo: int, codigo_anterior: string, codigo_nuevo: string}>
     */
    public static function alinearCuentasCajaDesdeAsiento(int $cajaMovimientoId): array
    {
        if ($cajaMovimientoId <= 0) {
            return [];
        }

        $movimiento = Caja_Movimiento::query()
            ->with([
                'caja_movimiento_cuentacajas.cuentacajas.cuentacontables:id,codigo',
                'asientos.asiento_movimientos.cuentacontables:id,codigo',
            ])
            ->find($cajaMovimientoId);
        if ($movimiento === null) {
            return [];
        }

        $asiento = $movimiento->asientos;
        if ($asiento === null || $asiento->asiento_movimientos->isEmpty()) {
            return [];
        }

        $empresaId = (int) $movimiento->empresa_id;
        $cuentasEmpresa = Cuentacaja::query()
            ->with('cuentacontables:id,codigo')
            ->where('empresa_id', $empresaId)
            ->where('cuentacontable_id', '>', 0)
            ->get(['id', 'codigo', 'cuentacontable_id', 'empresa_id']);

        $catalogo = [];
        foreach ($cuentasEmpresa as $cuenta) {
            $catalogo[] = [
                'id' => (int) $cuenta->id,
                'codigo' => trim((string) $cuenta->codigo),
                'codigo_contable' => trim((string) ($cuenta->cuentacontables->codigo ?? '')),
            ];
        }

        $lineasCaja = [];
        foreach ($movimiento->caja_movimiento_cuentacajas as $linea) {
            $lineasCaja[] = [
                'linea_id' => (int) $linea->id,
                'cuentacaja_id' => (int) $linea->cuentacaja_id,
                'codigo' => trim((string) ($linea->cuentacajas->codigo ?? '')),
                'codigo_contable' => trim((string) ($linea->cuentacajas->cuentacontables->codigo ?? '')),
                'monto' => (float) $linea->monto,
            ];
        }

        $lineasAsiento = [];
        foreach ($asiento->asiento_movimientos as $linea) {
            $lineasAsiento[] = [
                'codigo_contable' => trim((string) ($linea->cuentacontables->codigo ?? '')),
                'monto' => (float) $linea->monto,
            ];
        }

        $cambios = self::resolverReasignaciones($lineasCaja, $lineasAsiento, $catalogo);
        foreach ($cambios as $cambio) {
            Caja_Movimiento_Cuentacaja::query()
                ->where('id', $cambio['linea_id'])
                ->where('caja_movimiento_id', $cajaMovimientoId)
                ->update(['cuentacaja_id' => $cambio['cuentacaja_id_nuevo']]);
            Log::info('caja.ie.banco_asiento.reasignada', [
                'caja_movimiento_id' => $cajaMovimientoId,
                'linea_id' => $cambio['linea_id'],
                'codigo_anterior' => $cambio['codigo_anterior'],
                'codigo_nuevo' => $cambio['codigo_nuevo'],
            ]);
        }

        return $cambios;
    }

    public static function normalizarCodigoContable(string $codigo): string
    {
        $digits = preg_replace('/\D+/', '', trim($codigo)) ?? '';
        $digits = ltrim($digits, '0');

        return $digits;
    }

    private static function mismoImporte(float $importeAsiento, float $montoCaja): bool
    {
        // El signo distingue las dos piernas de una transferencia (entrada / salida)
        // cuando el importe absoluto es el mismo.
        return abs($importeAsiento - $montoCaja) < 0.02;
    }
}

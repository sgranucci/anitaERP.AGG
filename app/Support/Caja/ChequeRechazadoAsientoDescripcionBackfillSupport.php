<?php

namespace App\Support\Caja;

use App\Models\Caja\Cheque;
use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Support\Configuracion\EntornoEmpresaSupport;
use InvalidArgumentException;

/**
 * Ferli: reescribe observación de asientos de NDR por cheque rechazado
 * al formato estándar Rech.#nro int.interno BANCO (mayor / CC).
 */
final class ChequeRechazadoAsientoDescripcionBackfillSupport
{
    /**
     * @return array{
     *   cheques:int,
     *   asientos:int,
     *   cabeceras_a_actualizar:int,
     *   movimientos_a_actualizar:int,
     *   cabeceras_actualizadas:int,
     *   movimientos_actualizados:int,
     *   sin_asiento:int,
     *   filas:list<array<string,mixed>>
     * }
     */
    public static function ejecutar(bool $persistir): array
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            throw new InvalidArgumentException(
                'Este backfill solo corre en Ferli (EMPRESA=Calzados Ferli). Ahora: '.EntornoEmpresaSupport::codigo().'.'
            );
        }

        $cheques = Cheque::query()
            ->with(['bancos', 'ventaNd'])
            ->whereNotNull('venta_nd_id')
            ->where('venta_nd_id', '>', 0)
            ->orderBy('id')
            ->get();

        $cabA = 0;
        $movA = 0;
        $cabOk = 0;
        $movOk = 0;
        $sinAsiento = 0;
        $asientosTocados = 0;
        $filas = [];

        foreach ($cheques as $cheque) {
            $nuevo = ChequeRechazoDescripcionSupport::detalleMayor($cheque);
            $ventaId = (int) $cheque->venta_nd_id;
            $asientos = Asiento::query()
                ->where('venta_id', $ventaId)
                ->orderBy('id')
                ->get(['id', 'observacion', 'numeroasiento', 'fecha', 'empresa_id', 'venta_id']);

            if ($asientos->isEmpty()) {
                $sinAsiento++;
                $filas[] = [
                    'cheque_id' => (int) $cheque->id,
                    'venta_id' => $ventaId,
                    'codigo' => (string) ($cheque->ventaNd->codigo ?? ''),
                    'asiento_id' => null,
                    'obs_actual' => null,
                    'obs_nueva' => $nuevo,
                    'cab_update' => false,
                    'movs_update' => 0,
                    'movs_total' => 0,
                    'sin_asiento' => true,
                ];
                continue;
            }

            foreach ($asientos as $asiento) {
                $asientosTocados++;
                $cabOld = (string) ($asiento->observacion ?? '');
                $cabUpdate = $cabOld !== $nuevo;
                if ($cabUpdate) {
                    $cabA++;
                }

                $movs = Asiento_Movimiento::query()
                    ->where('asiento_id', (int) $asiento->id)
                    ->orderBy('id')
                    ->get(['id', 'observacion']);

                $movUpdateIds = [];
                foreach ($movs as $mov) {
                    if ((string) ($mov->observacion ?? '') !== $nuevo) {
                        $movUpdateIds[] = (int) $mov->id;
                    }
                }
                $movA += count($movUpdateIds);

                $filas[] = [
                    'cheque_id' => (int) $cheque->id,
                    'venta_id' => $ventaId,
                    'codigo' => (string) ($cheque->ventaNd->codigo ?? ''),
                    'asiento_id' => (int) $asiento->id,
                    'numeroasiento' => $asiento->numeroasiento,
                    'fecha' => (string) ($asiento->fecha ?? ''),
                    'empresa_id' => (int) ($asiento->empresa_id ?? 0),
                    'obs_actual' => $cabOld,
                    'obs_nueva' => $nuevo,
                    'cab_update' => $cabUpdate,
                    'movs_update' => count($movUpdateIds),
                    'movs_total' => $movs->count(),
                    'sin_asiento' => false,
                ];

                if (! $persistir) {
                    continue;
                }

                if ($cabUpdate) {
                    $asiento->observacion = $nuevo;
                    $asiento->save();
                    $cabOk++;
                }

                if ($movUpdateIds !== []) {
                    foreach ($movs as $mov) {
                        if (! in_array((int) $mov->id, $movUpdateIds, true)) {
                            continue;
                        }
                        $mov->observacion = $nuevo;
                        $mov->save();
                        $movOk++;
                    }
                }
            }
        }

        return [
            'cheques' => $cheques->count(),
            'asientos' => $asientosTocados,
            'cabeceras_a_actualizar' => $cabA,
            'movimientos_a_actualizar' => $movA,
            'cabeceras_actualizadas' => $cabOk,
            'movimientos_actualizados' => $movOk,
            'sin_asiento' => $sinAsiento,
            'filas' => $filas,
        ];
    }
}

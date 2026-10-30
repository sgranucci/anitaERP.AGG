<?php

namespace App\Support\Caja;

use App\Models\Caja\Cheque;
use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Support\Configuracion\SistemaNumeradorSupport;

/**
 * Completa nro. de boleta en depósitos CHT que quedaron vacíos.
 * Una boleta = misma empresa, fecha y cuenta de depósito.
 */
final class ChequeDepositoBoletaSupport
{
    /**
     * @return array{grupos:int, cheques:int, desde:int, hasta:int}
     */
    public static function completarFaltantes(): array
    {
        $filas = Cheque::query()
            ->where('origen', 'R')
            ->whereNotNull('fecha_deposito')
            ->where(function ($q) {
                $q->whereNull('nro_boleta_deposito')->orWhere('nro_boleta_deposito', '');
            })
            ->orderBy('fecha_deposito')
            ->orderBy('id')
            ->get(['id', 'empresa_id', 'fecha_deposito', 'cuentacaja_deposito_id', 'asiento_acreditacion_id']);

        $grupos = [];
        foreach ($filas as $cheque) {
            $fecha = $cheque->fecha_deposito instanceof \DateTimeInterface
                ? $cheque->fecha_deposito->format('Y-m-d')
                : substr((string) $cheque->fecha_deposito, 0, 10);
            $key = ((int) $cheque->empresa_id).'|'.$fecha.'|'.(int) ($cheque->cuentacaja_deposito_id ?? 0);
            if (! isset($grupos[$key])) {
                $grupos[$key] = [
                    'empresa_id' => (int) $cheque->empresa_id,
                    'ids' => [],
                    'asientos' => [],
                ];
            }
            $grupos[$key]['ids'][] = (int) $cheque->id;
            $asientoId = (int) ($cheque->asiento_acreditacion_id ?? 0);
            if ($asientoId > 0) {
                $grupos[$key]['asientos'][$asientoId] = $asientoId;
            }
        }

        $desde = 0;
        $hasta = 0;
        $cheques = 0;
        foreach ($grupos as $grupo) {
            $empresaId = (int) $grupo['empresa_id'];
            if ($empresaId <= 0) {
                continue;
            }
            $numero = SistemaNumeradorSupport::reservarSiguienteBoletaDeposito($empresaId);
            $nro = (string) $numero;
            if ($desde === 0) {
                $desde = $numero;
            }
            $hasta = $numero;

            foreach (Cheque::query()->whereIn('id', $grupo['ids'])->get() as $cheque) {
                $cheque->nro_boleta_deposito = $nro;
                $cheque->save();
                $cheques++;
            }
            self::marcarAsientos($grupo['asientos'], $nro);
        }

        return [
            'grupos' => count($grupos),
            'cheques' => $cheques,
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    /**
     * @param  array<int, int>  $asientoIds
     */
    private static function marcarAsientos(array $asientoIds, string $nro): void
    {
        if ($asientoIds === []) {
            return;
        }
        $marca = 'boleta '.$nro;
        foreach (Asiento::query()->whereIn('id', array_values($asientoIds))->get() as $asiento) {
            $obs = trim((string) $asiento->observacion);
            if ($obs !== '' && str_contains($obs, 'boleta ')) {
                continue;
            }
            $asiento->observacion = $obs === '' ? $marca : $obs.' '.$marca;
            $asiento->save();
        }
        Asiento_Movimiento::withoutEvents(function () use ($asientoIds, $marca): void {
            foreach (Asiento_Movimiento::query()->whereIn('asiento_id', array_values($asientoIds))->get() as $movimiento) {
                $obs = trim((string) $movimiento->observacion);
                if ($obs !== '' && str_contains($obs, 'boleta ')) {
                    continue;
                }
                $movimiento->observacion = $obs === '' ? $marca : $obs.' '.$marca;
                $movimiento->save();
            }
        });
    }
}

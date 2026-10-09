<?php

namespace App\Support\Caja;

use App\Models\Caja\Cheque;
use Illuminate\Support\Facades\DB;

/**
 * Corrige numerocheque de CHT importados de Anita cuando quedó igual al interno.
 *
 * Anita deja cter_nro_cheque en 0 y el número va en cter_nro_e_cheq (ya guardado
 * en nro_echeq). No toca cheques propios ni CHT cuyo número ya difiere del interno.
 */
final class ChequeTerceroNumeroChequeBackfillSupport
{
    /**
     * @return array{
     *   candidatos:int,
     *   a_actualizar:int,
     *   sin_numero:int,
     *   desde_nro_echeq:int,
     *   desde_anita:int,
     *   actualizados:int,
     *   ejemplos:list<array<string,mixed>>
     * }
     */
    public static function ejecutar(bool $persistir): array
    {
        $filas = Cheque::query()
            ->where('origen', 'R')
            ->whereNotNull('nro_interno_anita')
            ->whereRaw('CAST(numerocheque AS CHAR) = CAST(nro_interno_anita AS CHAR)')
            ->get(['id', 'numerocheque', 'nro_interno_anita', 'nro_echeq', 'negociable']);

        $aActualizar = 0;
        $sinNumero = 0;
        $desdeNroEcheq = 0;
        $desdeAnita = 0;
        $actualizados = 0;
        $ejemplos = [];
        $updates = [];

        foreach ($filas as $ch) {
            $interno = (string) (int) $ch->nro_interno_anita;
            $nuevo = ChequeTerceroCtermaeAnitaMapper::numeroUtil($ch->nro_echeq);
            $origen = 'nro_echeq';
            if ($nuevo === null || $nuevo === $interno) {
                $fila = ChequeAnitaSyncSupport::leerCtermaePorNroInterno((int) $ch->nro_interno_anita);
                $nuevo = $fila !== null
                    ? ChequeTerceroCtermaeAnitaMapper::numerochequeDesdeFila($fila)
                    : $interno;
                $origen = 'anita';
            }

            if ($nuevo === '' || $nuevo === $interno) {
                $sinNumero++;
                continue;
            }

            $aActualizar++;
            if ($origen === 'anita') {
                $desdeAnita++;
            } else {
                $desdeNroEcheq++;
            }

            if (count($ejemplos) < 8) {
                $ejemplos[] = [
                    'id' => (int) $ch->id,
                    'interno' => (int) $ch->nro_interno_anita,
                    'antes' => (string) $ch->numerocheque,
                    'despues' => $nuevo,
                    'origen' => $origen,
                    'negociable' => (string) ($ch->negociable ?? ''),
                ];
            }

            $updates[] = [
                'id' => (int) $ch->id,
                'numerocheque' => mb_substr($nuevo, 0, 50),
            ];
        }

        if ($persistir && $updates !== []) {
            DB::transaction(function () use ($updates, &$actualizados) {
                foreach (array_chunk($updates, 500) as $chunk) {
                    foreach ($chunk as $u) {
                        Cheque::query()->whereKey($u['id'])->update([
                            'numerocheque' => $u['numerocheque'],
                            'updated_at' => now(),
                        ]);
                        $actualizados++;
                    }
                }
            });
        }

        return [
            'candidatos' => $filas->count(),
            'a_actualizar' => $aActualizar,
            'sin_numero' => $sinNumero,
            'desde_nro_echeq' => $desdeNroEcheq,
            'desde_anita' => $desdeAnita,
            'actualizados' => $actualizados,
            'ejemplos' => $ejemplos,
        ];
    }
}

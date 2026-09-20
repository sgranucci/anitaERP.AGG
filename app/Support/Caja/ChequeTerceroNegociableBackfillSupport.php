<?php

namespace App\Support\Caja;

use App\Models\Caja\Cheque;
use Illuminate\Support\Facades\DB;

/**
 * Recupera negociable / nro_echeq de CHT desde Anita (cter_interior = '3' → e-cheq).
 */
final class ChequeTerceroNegociableBackfillSupport
{
    /**
     * @return array{
     *   anita_filas:int,
     *   erp_total:int,
     *   sin_match:int,
     *   ya_ok:int,
     *   a_e:int,
     *   a_n:int,
     *   actualizados:int,
     *   ejemplos_e:list<array<string,mixed>>,
     *   ejemplos_n:list<array<string,mixed>>
     * }
     */
    public static function ejecutar(bool $persistir): array
    {
        $minNi = (int) Cheque::query()->where('origen', 'R')->whereNotNull('nro_interno_anita')->min('nro_interno_anita');
        $maxNi = (int) Cheque::query()->where('origen', 'R')->whereNotNull('nro_interno_anita')->max('nro_interno_anita');

        $filasAnita = $minNi > 0
            ? ChequeAnitaSyncSupport::listarCtermaeInstrumentoEntre($minNi, $maxNi)
            : [];

        $porNi = [];
        foreach ($filasAnita as $row) {
            $ni = (int) preg_replace('/\D/', '', (string) ($row->cter_nro_interno ?? '0'));
            if ($ni <= 0) {
                continue;
            }
            $porNi[$ni] = $row;
        }

        $erp = Cheque::query()
            ->where('origen', 'R')
            ->whereNotNull('nro_interno_anita')
            ->get(['id', 'nro_interno_anita', 'negociable', 'nro_echeq', 'numerocheque']);

        $sinMatch = 0;
        $yaOk = 0;
        $aE = 0;
        $aN = 0;
        $actualizados = 0;
        $ejemplosE = [];
        $ejemplosN = [];
        $updates = [];

        foreach ($erp as $ch) {
            $ni = (int) $ch->nro_interno_anita;
            if (! isset($porNi[$ni])) {
                $sinMatch++;
                continue;
            }

            $row = $porNi[$ni];
            $wantNeg = ChequeTerceroCtermaeAnitaMapper::negociableDesdeInterior($row->cter_interior ?? null);
            $wantNro = null;
            if ($wantNeg === 'E') {
                $nroAnita = trim((string) ($row->cter_nro_e_cheq ?? ''));
                if ($nroAnita !== '' && $nroAnita !== '0') {
                    $wantNro = mb_substr($nroAnita, 0, 50);
                } else {
                    $nro = trim((string) ($ch->numerocheque ?? ''));
                    $wantNro = $nro !== '' ? mb_substr($nro, 0, 50) : null;
                }
            }

            $haveNeg = strtoupper(trim((string) ($ch->negociable ?? '')));
            $haveNro = trim((string) ($ch->nro_echeq ?? '')) ?: null;
            $mismoNeg = $haveNeg === $wantNeg;
            $mismoNro = ($wantNeg === 'E')
                ? ($haveNro === $wantNro)
                : ($haveNro === null || $haveNro === '');

            if ($mismoNeg && $mismoNro) {
                $yaOk++;
                continue;
            }

            if ($wantNeg === 'E') {
                $aE++;
                if (count($ejemplosE) < 8) {
                    $ejemplosE[] = [
                        'id' => (int) $ch->id,
                        'ni' => $ni,
                        'nro' => (string) $ch->numerocheque,
                        'have' => $haveNeg !== '' ? $haveNeg : 'null',
                        'nro_echeq' => $wantNro,
                    ];
                }
            } else {
                $aN++;
                if (count($ejemplosN) < 5) {
                    $ejemplosN[] = [
                        'id' => (int) $ch->id,
                        'ni' => $ni,
                        'nro' => (string) $ch->numerocheque,
                        'have' => $haveNeg !== '' ? $haveNeg : 'null',
                    ];
                }
            }

            $updates[] = [
                'id' => (int) $ch->id,
                'negociable' => $wantNeg,
                'nro_echeq' => $wantNro,
            ];
        }

        if ($persistir && $updates !== []) {
            DB::transaction(function () use ($updates, &$actualizados) {
                foreach (array_chunk($updates, 500) as $chunk) {
                    foreach ($chunk as $u) {
                        Cheque::query()->whereKey($u['id'])->update([
                            'negociable' => $u['negociable'],
                            'nro_echeq' => $u['nro_echeq'],
                            'updated_at' => now(),
                        ]);
                        $actualizados++;
                    }
                }
            });
        }

        return [
            'anita_filas' => count($filasAnita),
            'erp_total' => $erp->count(),
            'sin_match' => $sinMatch,
            'ya_ok' => $yaOk,
            'a_e' => $aE,
            'a_n' => $aN,
            'actualizados' => $actualizados,
            'ejemplos_e' => $ejemplosE,
            'ejemplos_n' => $ejemplosN,
        ];
    }
}

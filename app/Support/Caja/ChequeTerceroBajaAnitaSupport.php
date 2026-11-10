<?php

namespace App\Support\Caja;

use App\ApiAnita;
use App\Models\Caja\Cheque;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\Log;

/**
 * Da de baja un CHT en Anita (ctermae): sale de cartera.
 *
 * Cartera Anita = cter_fecha_baja = 0 y cter_estado en (' ', 'N').
 * Anulación de cobranza / reversión: cter_estado = 'A' + cter_fecha_baja.
 */
final class ChequeTerceroBajaAnitaSupport
{
    public static function darDeBaja(Cheque $cheque, ?string $fechaBajaYmd = null): bool
    {
        $nroInterno = (int) ($cheque->nro_interno_anita ?? 0);
        if ($nroInterno <= 0) {
            return true;
        }

        if (strtoupper((string) ($cheque->origen ?? '')) !== 'R') {
            return true;
        }

        return self::darDeBajaPorNroInterno($nroInterno, $fechaBajaYmd, (int) ($cheque->id ?? 0));
    }

    /**
     * @param  iterable<int, Cheque>  $cheques
     * @return list<string>
     */
    public static function darDeBajaColeccion(iterable $cheques, ?string $fechaBajaYmd = null): array
    {
        $fallos = [];
        foreach ($cheques as $cheque) {
            if (! $cheque instanceof Cheque) {
                continue;
            }
            if (self::darDeBaja($cheque, $fechaBajaYmd)) {
                continue;
            }
            $nro = trim((string) ($cheque->numerocheque ?? ''));
            $interno = (int) ($cheque->nro_interno_anita ?? 0);
            $fallos[] = ($nro !== '' ? $nro : '#'.$cheque->id)
                .' (interno '.$interno.')';
        }

        return $fallos;
    }

    public static function darDeBajaPorNroInterno(int $nroInterno, ?string $fechaBajaYmd = null, int $chequeId = 0): bool
    {
        if ($nroInterno <= 0) {
            return false;
        }

        $fecha = ChequePropioCpromaeAnitaMapper::ymd((string) ($fechaBajaYmd ?? date('Y-m-d')));
        if ($fecha === '0' || $fecha === '') {
            $fecha = date('Ymd');
        }

        $api = new ApiAnita();
        $where = ' WHERE cter_nro_interno = '.$nroInterno.' ';

        try {
            $api->apiCallEscritura([
                'acc' => 'update',
                'tabla' => 'ctermae',
                'sistema' => 'che_ban',
                'valores' => "cter_estado = 'A', cter_fecha_baja = '".$fecha."'",
                'whereArmado' => $where,
            ]);

            return true;
        } catch (\Throwable $e) {
            // Schema sin cter_fecha_baja: al menos el estado.
            try {
                $api->apiCallEscritura([
                    'acc' => 'update',
                    'tabla' => 'ctermae',
                    'sistema' => 'che_ban',
                    'valores' => "cter_estado = 'A'",
                    'whereArmado' => $where,
                ]);

                return true;
            } catch (\Throwable $e2) {
                Log::warning('Cheque CHT baja Anita falló', [
                    'nro_interno' => $nroInterno,
                    'cheque_id' => $chequeId > 0 ? $chequeId : null,
                    'ferli' => EntornoEmpresaSupport::esFerli(),
                    'error' => $e2->getMessage(),
                    'error_con_fecha_baja' => $e->getMessage(),
                ]);

                return false;
            }
        }
    }
}

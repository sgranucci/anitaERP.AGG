<?php

namespace App\Support\Caja;

use App\ApiAnita;
use App\Models\Caja\Cheque;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\Log;

/**
 * Marca rechazo de cheque de terceros en Anita (ctermae.cter_estado = R).
 */
final class ChequeTerceroRechazoAnitaSupport
{
    public static function marcarRechazo(Cheque $cheque, ?string $fechaRechazo = null): bool
    {
        $nroInterno = (int) ($cheque->nro_interno_anita ?? 0);
        if ($nroInterno <= 0) {
            return false;
        }

        $fecha = ChequePropioCpromaeAnitaMapper::ymd((string) ($fechaRechazo ?? $cheque->fecha_rechazo ?? date('Y-m-d')));
        if ($fecha === '0') {
            $fecha = date('Ymd');
        }

        $set = "cter_estado = 'R'";

        $data = [
            'acc' => 'update',
            'tabla' => 'ctermae',
            'sistema' => 'che_ban',
            'valores' => $set,
            'whereArmado' => ' WHERE cter_nro_interno = '.$nroInterno.' ',
        ];

        try {
            $api = new ApiAnita();
            $api->apiCallEscritura($data);

            // Best-effort: algunos entornos tienen cter_fecha_baja.
            try {
                $api->apiCallEscritura([
                    'acc' => 'update',
                    'tabla' => 'ctermae',
                    'sistema' => 'che_ban',
                    'valores' => "cter_fecha_baja = '".$fecha."'",
                    'whereArmado' => ' WHERE cter_nro_interno = '.$nroInterno.' ',
                ]);
            } catch (\Throwable) {
                // Schema sin cter_fecha_baja: ignorar.
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Cheque CHT rechazo Anita falló', [
                'nro_interno' => $nroInterno,
                'cheque_id' => $cheque->id,
                'ferli' => EntornoEmpresaSupport::esFerli(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}

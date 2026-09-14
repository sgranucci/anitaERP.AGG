<?php

namespace App\Support\Caja;

use App\ApiAnita;
use App\Models\Caja\Cheque;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\Log;

/**
 * Caución / garantía bancaria de CHT en Anita (cter_nro_caucion).
 */
final class ChequeTerceroCaucionAnitaSupport
{
    public static function marcarCaucion(Cheque $cheque, string $nroCaucion): bool
    {
        $nroInterno = (int) ($cheque->nro_interno_anita ?? 0);
        if ($nroInterno <= 0) {
            return false;
        }

        $nro = trim($nroCaucion);
        if ($nro === '') {
            $nro = '0';
        }
        $nro = mb_substr($nro, 0, 20);

        $data = [
            'acc' => 'update',
            'tabla' => 'ctermae',
            'sistema' => 'che_ban',
            'valores' => "cter_nro_caucion = '".addslashes($nro)."'",
            'whereArmado' => ' WHERE cter_nro_interno = '.$nroInterno.' ',
        ];

        try {
            $api = new ApiAnita();
            $api->apiCallEscritura($data);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Cheque CHT caución Anita falló', [
                'nro_interno' => $nroInterno,
                'cheque_id' => $cheque->id,
                'ferli' => EntornoEmpresaSupport::esFerli(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public static function liberarCaucion(Cheque $cheque): bool
    {
        return self::marcarCaucion($cheque, '0');
    }
}

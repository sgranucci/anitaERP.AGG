<?php

namespace App\Support\Caja;

use App\ApiAnita;
use App\Models\Caja\Cheque;
use App\Models\Caja\Cuentacaja;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\Log;

/**
 * Depósito de cheque de terceros en Anita (ctermae) y ERP.
 */
final class ChequeTerceroDepositoAnitaSupport
{
    public static function marcarDeposito(Cheque $cheque, string $fechaDepositoYmd, ?Cuentacaja $cuentacaja = null, ?string $nroBoleta = null): bool
    {
        $nroInterno = (int) ($cheque->nro_interno_anita ?? 0);
        if ($nroInterno <= 0) {
            return false;
        }

        $fecha = ChequePropioCpromaeAnitaMapper::ymd($fechaDepositoYmd);
        if ($fecha === '0') {
            $fecha = date('Ymd');
        }

        $cuenta = '0';
        if ($cuentacaja) {
            $cuenta = trim((string) ($cuentacaja->codigo ?? ''));
            if ($cuenta === '') {
                $cuenta = (string) ((int) $cuentacaja->id);
            }
        }

        $boleta = trim((string) ($nroBoleta ?? $cheque->nro_boleta_deposito ?? '0'));
        if ($boleta === '') {
            $boleta = '0';
        }

        $set = "
            cter_fecha_dep = '".$fecha."',
            cter_cuenta = '".addslashes($cuenta)."',
            cter_nro_boleta = '".addslashes(mb_substr($boleta, 0, 20))."',
            cter_estado = '*'
        ";

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

            return true;
        } catch (\Throwable $e) {
            Log::warning('Cheque CHT depósito Anita falló', [
                'nro_interno' => $nroInterno,
                'cheque_id' => $cheque->id,
                'ferli' => EntornoEmpresaSupport::esFerli(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}

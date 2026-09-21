<?php

namespace App\Support\Caja;

use App\ApiAnita;
use App\Models\Caja\Cheque;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\Log;

/**
 * Endoso / entrega de cheque de terceros en Anita (ctermae).
 * Marca salida de cartera hacia acreedor (OP / egreso).
 */
final class ChequeTerceroEndosoAnitaSupport
{
    /**
     * @param  array{
     *   fecha_acreed?:string,
     *   nro_op?:string|int,
     *   cedio_a?:string,
     *   entregado_a?:string,
     *   proveedor_codigo?:string
     * }  $ctx
     */
    public static function marcarEndoso(Cheque $cheque, array $ctx = []): bool
    {
        $nroInterno = (int) ($cheque->nro_interno_anita ?? 0);
        if ($nroInterno <= 0) {
            return false;
        }

        $fecha = ChequePropioCpromaeAnitaMapper::ymd((string) ($ctx['fecha_acreed'] ?? $cheque->fechaemision ?? date('Y-m-d')));
        if ($fecha === '0') {
            $fecha = date('Ymd');
        }

        $cedioA = trim((string) ($ctx['cedio_a'] ?? $ctx['proveedor_codigo'] ?? '0'));
        if ($cedioA === '') {
            $cedioA = '0';
        }
        $cedioA = str_pad(ltrim(preg_replace('/\D/', '', $cedioA) ?: '0', '0') ?: '0', 6, '0', STR_PAD_LEFT);

        $entregadoA = self::recortar((string) ($ctx['entregado_a'] ?? ''), 40);
        $nroOp = (string) ((int) preg_replace('/\D/', '', (string) ($ctx['nro_op'] ?? '0')));

        $set = "
            cter_fecha_acreed = '".$fecha."',
            cter_cedio_a = '".$cedioA."',
            cter_nro_op = '".$nroOp."',
            cter_entregado_a = '".addslashes($entregadoA)."'
        ";

        // Ferli: schema sin algunas columnas extendidas; campos de endoso sí existen en mid probe.
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
            Log::warning('Cheque CHT endoso Anita falló', [
                'nro_interno' => $nroInterno,
                'cheque_id' => $cheque->id,
                'ferli' => EntornoEmpresaSupport::esFerli(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  iterable<Cheque>  $cheques
     * @param  array<string, mixed>  $ctx
     */
    public static function marcarEndosoColeccion(iterable $cheques, array $ctx = []): int
    {
        $ok = 0;
        foreach ($cheques as $cheque) {
            if (! $cheque instanceof Cheque) {
                continue;
            }
            if ((string) ($cheque->origen ?? '') !== 'R') {
                continue;
            }
            if (self::marcarEndoso($cheque, $ctx)) {
                $ok++;
            }
        }

        return $ok;
    }

    /**
     * Quita el endoso en ctermae cuando la OP se anula o se revierte y el cheque vuelve a cartera.
     *
     * @param  iterable<Cheque>  $cheques
     */
    public static function desmarcarEndosoColeccion(iterable $cheques): int
    {
        $ok = 0;
        foreach ($cheques as $cheque) {
            if (! $cheque instanceof Cheque) {
                continue;
            }
            if ((string) ($cheque->origen ?? '') !== 'R') {
                continue;
            }
            if (self::desmarcarEndoso($cheque)) {
                $ok++;
            }
        }

        return $ok;
    }

    public static function desmarcarEndoso(Cheque $cheque): bool
    {
        $nroInterno = (int) ($cheque->nro_interno_anita ?? 0);
        if ($nroInterno <= 0) {
            return false;
        }

        $entregadoA = self::recortar((string) ($cheque->anombrede ?? ''), 40);
        $set = "
            cter_fecha_acreed = '0',
            cter_cedio_a = '0',
            cter_nro_op = '0',
            cter_entregado_a = '".addslashes($entregadoA)."'
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
            Log::warning('Cheque CHT desmarcar endoso Anita falló', [
                'nro_interno' => $nroInterno,
                'cheque_id' => $cheque->id,
                'ferli' => EntornoEmpresaSupport::esFerli(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private static function recortar(string $valor, int $max): string
    {
        $valor = trim($valor);
        if (function_exists('mb_substr')) {
            return mb_substr($valor, 0, $max);
        }

        return substr($valor, 0, $max);
    }
}

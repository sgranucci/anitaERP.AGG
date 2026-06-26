<?php

namespace App\Support\Compras;

use App\ApiAnita;
use Illuminate\Support\Facades\DB;

/**
 * Evita asignar numerorequisicion ya usado en ERP o en reqmae (Anita compras).
 */
final class RequisicionAnitaColisionSupport
{
    public static function sistemaCompras(): string
    {
        return (string) config('requisicion.anita.sistema_compras', 'compras');
    }

    public static function tablaCabecera(): string
    {
        return (string) config('requisicion.anita.tabla_cabecera', 'reqmae');
    }

    public static function maxNumeroReqmaeGlobal(): int
    {
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => self::sistemaCompras(),
            'tabla' => self::tablaCabecera(),
            'campos' => 'max(reqm_nro) as max_nro',
        ]);

        $fila = ApiAnita::primeraFilaLista($raw);

        return max(0, (int) ($fila->max_nro ?? 0));
    }

    public static function existeNroEnReqmae(int $nro): bool
    {
        if ($nro <= 0) {
            return false;
        }

        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => self::sistemaCompras(),
            'tabla' => self::tablaCabecera(),
            'campos' => 'reqm_nro',
            'whereArmado' => ' WHERE reqm_nro = '.(int) $nro,
            'limit' => 'FIRST 1',
        ]);

        return ApiAnita::primeraFilaLista($raw) !== null;
    }

    /**
     * @param  int|null  $excluirRequisicionId  Al renumerar, excluir la fila actual en ERP.
     */
    public static function numeroOcupadoParaNuevaAsignacion(int $nro, ?int $excluirRequisicionId = null): bool
    {
        if ($nro <= 0) {
            return false;
        }

        $query = DB::table('requisicion')->where('numerorequisicion', $nro);
        if ($excluirRequisicionId !== null && $excluirRequisicionId > 0) {
            $query->where('id', '!=', $excluirRequisicionId);
        }
        if ($query->exists()) {
            return true;
        }

        return self::existeNroEnReqmae($nro);
    }

    public static function primerNumeroDisponible(int $desde, ?int $excluirRequisicionId = null): int
    {
        $nro = max(1, $desde);
        for ($intentos = 0; $intentos < 500; $intentos++) {
            if (! self::numeroOcupadoParaNuevaAsignacion($nro, $excluirRequisicionId)) {
                return $nro;
            }
            $nro++;
        }

        throw new \RuntimeException(
            'No se encontró número de requisición libre desde '.$desde.' (numerador único ERP/Anita).'
        );
    }
}

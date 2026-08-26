<?php

namespace App\Support\Compras\AnitaSync\Requisicion;

use App\ApiAnita;
use App\Support\Compras\RequisicionAnitaColisionSupport;
use Illuminate\Support\Facades\Log;

/**
 * aprobc_nro_int_ap: mismo numerador que Anita desktop (numabm código 73, a-compprov).
 */
final class RequisicionAnitaAprobcompNumeracionSupport
{
    public static function sistemaShared(): string
    {
        return (string) config('requisicion.anita.sistema_shared', 'shared');
    }

    public static function whereArmadoNumabm(): string
    {
        $cfg = config('requisicion.anita.numerador_aprobcomp', []);
        $codigo = (int) ($cfg['codigo'] ?? 73);
        if ($codigo > 0) {
            return ' WHERE numa_codigo = '.$codigo;
        }

        $sistema = self::escSqlLiteral((string) ($cfg['sistema_abm'] ?? 'compras'));
        $programa = self::escSqlLiteral((string) ($cfg['programa'] ?? 'a-compprov'));
        $referencia = self::escSqlLiteral((string) ($cfg['referencia'] ?? '1'));

        return " WHERE numa_sistema='{$sistema}' AND numa_programa='{$programa}' AND numa_referencia='{$referencia}'";
    }

    public static function leerUltimoNumeroNumabm(): int
    {
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'list',
            'sistema' => self::sistemaShared(),
            'tabla' => 'numabm',
            'campos' => 'numa_ult_numero',
            'whereArmado' => self::whereArmadoNumabm(),
        ], 'aprobcomp numabm lectura');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException('No se pudo leer numabm aprobcomp (a-compprov): '.$err);
        }

        $fila = ApiAnita::primeraFilaLista((string) $raw);
        if ($fila === null || ! isset($fila->numa_ult_numero)) {
            throw new \RuntimeException('numabm aprobcomp inexistente (código 73 / a-compprov).');
        }

        return max(0, (int) $fila->numa_ult_numero);
    }

    public static function actualizarNumerador(int $numero): void
    {
        if ($numero <= 0) {
            throw new \InvalidArgumentException('nro_int_ap Anita inválido.');
        }

        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'update',
            'sistema' => self::sistemaShared(),
            'tabla' => 'numabm',
            'valores' => 'numa_ult_numero = '.(int) $numero,
            'whereArmado' => self::whereArmadoNumabm(),
        ], 'aprobcomp numabm update');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException('No se pudo actualizar numabm aprobcomp: '.$err);
        }
    }

    public static function maxNroIntApEnAprobcomp(): int
    {
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => RequisicionAnitaColisionSupport::sistemaCompras(),
            'tabla' => RequisicionAnitaAprobcompMapper::TABLA,
            'campos' => 'MAX(aprobc_nro_int_ap) as maxn',
        ]);

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException('No se pudo leer MAX(aprobc_nro_int_ap): '.$err);
        }

        $fila = ApiAnita::primeraFilaLista($raw);

        return max(0, (int) ($fila->maxn ?? 0));
    }

    public static function reservarSiguiente(): int
    {
        $siguiente = max(self::leerUltimoNumeroNumabm(), self::maxNroIntApEnAprobcomp()) + 1;
        self::actualizarNumerador($siguiente);

        Log::info('RequisicionAnitaAprobcomp: nro_int_ap reservado', [
            'nro_int_ap' => $siguiente,
        ]);

        return $siguiente;
    }

    private static function escSqlLiteral(string $value): string
    {
        return str_replace("'", "''", trim($value));
    }
}

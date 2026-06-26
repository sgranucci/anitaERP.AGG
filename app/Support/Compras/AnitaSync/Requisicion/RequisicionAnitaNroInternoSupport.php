<?php

namespace App\Support\Compras\AnitaSync\Requisicion;

use App\ApiAnita;
use App\Models\Compras\Requisicion_Articulo;
use App\Support\Compras\RequisicionAnitaNumeracionSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reserva reqv_nro_interno (numabm shared referencia 2, a-reqmae.c) como Anita desktop.
 */
final class RequisicionAnitaNroInternoSupport
{
    public static function referenciaNumerador(): string
    {
        return (string) config('requisicion.anita.numerador_linea_interno.referencia', '2');
    }

    public static function whereArmadoNumabmLinea(): string
    {
        $cfg = config('requisicion.anita.numerador_linea_interno', []);
        $referencia = self::escSqlLiteral((string) ($cfg['referencia'] ?? '2'));
        $sistema = self::escSqlLiteral((string) ($cfg['sistema_abm'] ?? 'compras'));
        $programa = self::escSqlLiteral((string) ($cfg['programa'] ?? 'a-reqmae.c'));

        return " WHERE numa_sistema='{$sistema}' AND numa_programa='{$programa}' AND numa_referencia='{$referencia}'";
    }

    public static function leerUltimoNumeroNumabmLinea(): int
    {
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'list',
            'sistema' => RequisicionAnitaNumeracionSupport::sistemaShared(),
            'tabla' => 'numabm',
            'campos' => 'numa_ult_numero',
            'whereArmado' => self::whereArmadoNumabmLinea(),
        ], 'requisicion numabm linea lectura');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException('No se pudo leer numabm línea requisición (ref '.self::referenciaNumerador().'): '.$err);
        }

        $fila = ApiAnita::primeraFilaLista((string) $raw);
        if ($fila === null || ! isset($fila->numa_ult_numero)) {
            throw new \RuntimeException('numabm línea requisición inexistente (ref '.self::referenciaNumerador().').');
        }

        return max(0, (int) $fila->numa_ult_numero);
    }

    public static function actualizarNumeradorLinea(int $numero): void
    {
        if ($numero <= 0) {
            throw new \InvalidArgumentException('Número interno de línea inválido.');
        }

        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'update',
            'sistema' => RequisicionAnitaNumeracionSupport::sistemaShared(),
            'tabla' => 'numabm',
            'valores' => 'numa_ult_numero = '.(int) $numero,
            'whereArmado' => self::whereArmadoNumabmLinea(),
        ], 'requisicion numabm linea update');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException('No se pudo actualizar numabm línea requisición: '.$err);
        }
    }

    public static function reservarSiguiente(): int
    {
        $ultimoAnita = self::leerUltimoNumeroNumabmLinea();
        $maxErp = (int) DB::table('requisicion_articulo')->max('anita_nro_interno');
        $base = max($ultimoAnita, $maxErp);
        $siguiente = $base + 1;
        self::actualizarNumeradorLinea($siguiente);

        return $siguiente;
    }

    public function asignarInternoSiFalta(Requisicion_Articulo $linea): int
    {
        $existente = (int) ($linea->anita_nro_interno ?? 0);
        if ($existente > 0) {
            return $existente;
        }

        $nuevo = self::reservarSiguiente();
        $linea->forceFill(['anita_nro_interno' => $nuevo])->save();

        return $nuevo;
    }

    public static function registrarInternoAsignado(int $nroInterno): void
    {
        if ($nroInterno <= 0) {
            return;
        }

        try {
            $ultimoNumerador = self::leerUltimoNumeroNumabmLinea();
            $maxErp = (int) DB::table('requisicion_articulo')->max('anita_nro_interno');
            $objetivo = max($nroInterno, $ultimoNumerador, $maxErp);
            if ($objetivo > $ultimoNumerador) {
                self::actualizarNumeradorLinea($objetivo);
            }
        } catch (\Throwable $e) {
            Log::warning('RequisicionAnitaNroInterno: no se pudo alinear numabm línea', [
                'nro_interno' => $nroInterno,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function escSqlLiteral(string $value): string
    {
        return str_replace("'", "''", trim($value));
    }
}

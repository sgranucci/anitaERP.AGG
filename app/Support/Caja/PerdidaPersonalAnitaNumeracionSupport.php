<?php

namespace App\Support\Caja;

use App\ApiAnita;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Numeración de pérdidas de personal alineada a Anita numabm:
 * WHERE numa_sistema='caja' AND numa_programa='a-perdmae.c' AND numa_referencia='1'
 * (config perdida_personal_anita.numerador — no numa_codigo).
 */
final class PerdidaPersonalAnitaNumeracionSupport
{
    public static function sistemaShared(): string
    {
        return (string) config('perdida_personal_anita.numerador.sistema_shared', 'shared');
    }

    public static function whereArmadoNumabm(): string
    {
        $cfg = config('perdida_personal_anita.numerador', []);
        $sistema = self::escSqlLiteral((string) ($cfg['sistema_abm'] ?? 'caja'));
        $programa = self::escSqlLiteral((string) ($cfg['programa'] ?? 'a-perdmae.c'));
        $referencia = self::escSqlLiteral((string) ($cfg['referencia'] ?? '1'));

        return " WHERE numa_sistema='{$sistema}' AND numa_programa='{$programa}' AND numa_referencia='{$referencia}'";
    }

    public static function leerUltimoNumeroNumabm(): int
    {
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'list',
            'sistema' => self::sistemaShared(),
            'tabla' => (string) config('perdida_personal_anita.numerador.tabla', 'numabm'),
            'campos' => 'numa_ult_numero',
            'whereArmado' => self::whereArmadoNumabm(),
        ], 'perdida_personal numabm lectura');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException('No se pudo leer numabm Anita (a-perdmae.c): '.$err);
        }

        $fila = ApiAnita::primeraFilaLista((string) $raw);
        if ($fila === null || ! isset($fila->numa_ult_numero)) {
            throw new \RuntimeException(
                'numabm inexistente o sin numa_ult_numero (programa a-perdmae.c).'
            );
        }

        return max(0, (int) $fila->numa_ult_numero);
    }

    public static function actualizarNumeradorNumabm(int $numero): void
    {
        if ($numero <= 0) {
            throw new \InvalidArgumentException('Número de pérdida personal Anita inválido.');
        }

        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'update',
            'sistema' => self::sistemaShared(),
            'tabla' => (string) config('perdida_personal_anita.numerador.tabla', 'numabm'),
            'valores' => 'numa_ult_numero = '.(int) $numero,
            'whereArmado' => self::whereArmadoNumabm(),
        ], 'perdida_personal numabm update');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException('No se pudo actualizar numabm Anita (a-perdmae.c): '.$err);
        }
    }

    /** Máximo global: ERP + perdmae + numabm. */
    public static function ultimoNumeroGlobal(): int
    {
        $maxErp = (int) DB::table('perdida_personal')->max('numero');

        $maxPerdmae = 0;
        try {
            $maxPerdmae = self::maxNumeroPerdmae();
        } catch (\Throwable $e) {
            Log::warning('PerdidaPersonalAnitaNumeracion: no se pudo leer max perdmae', [
                'error' => $e->getMessage(),
            ]);
        }

        $maxNumerador = 0;
        try {
            $maxNumerador = self::leerUltimoNumeroNumabm();
        } catch (\Throwable $e) {
            Log::warning('PerdidaPersonalAnitaNumeracion: no se pudo leer numabm', [
                'error' => $e->getMessage(),
            ]);
        }

        return max($maxErp, $maxPerdmae, $maxNumerador);
    }

    public static function maxNumeroPerdmae(): int
    {
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => (string) config('perdida_personal_anita.sistema', 'caja'),
            'tabla' => (string) config('perdida_personal_anita.tabla', 'perdmae'),
            'campos' => 'perdm_nro',
        ]);

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException('No se pudo listar perdmae para max número: '.$err);
        }

        $filas = ApiAnita::decodificarListaFilas(is_string($raw) ? $raw : json_encode($raw));
        $max = 0;
        foreach ($filas as $fila) {
            $nro = (int) ($fila->perdm_nro ?? 0);
            if ($nro > $max) {
                $max = $nro;
            }
        }

        return $max;
    }

    /**
     * Reserva el siguiente número: max(ERP, perdmae, numabm) + 1 y actualiza numabm.
     */
    public static function reservarSiguiente(): int
    {
        $ultimo = self::ultimoNumeroGlobal();
        $siguiente = $ultimo + 1;
        self::actualizarNumeradorNumabm($siguiente);

        Log::info('PerdidaPersonalAnitaNumeracion: número reservado en numabm', [
            'programa' => (string) config('perdida_personal_anita.numerador.programa', 'a-perdmae.c'),
            'ultimo_global' => $ultimo,
            'asignado' => $siguiente,
        ]);

        return $siguiente;
    }

    public static function escSqlLiteral(string $value): string
    {
        return str_replace("'", "''", trim($value));
    }
}

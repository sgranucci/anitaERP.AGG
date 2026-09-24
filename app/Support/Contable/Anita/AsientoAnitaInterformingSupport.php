<?php

declare(strict_types=1);

namespace App\Support\Contable\Anita;

use App\ApiAnita;
use App\Support\Configuracion\EntornoEmpresaSupport;
use RuntimeException;

/**
 * Contabilización de asientos Anita solo para Interforming.
 *
 * Verificado 24/sep/2026:
 * - No hay filas en shared.numabm (a-ctamov.c) → el path AGG falla con «sin filas».
 * - Numerador: ventas.numerador clave "500" (base 500 + empresa en Anita desktop;
 *   instalación mono-empresa: fila concreta num_clave=500, ult≈72001).
 * - Esquema ctamov reducido (sin o_compra / umod), igual El Bierzo / Ferli.
 */
final class AsientoAnitaInterformingSupport
{
    /** Base Anita: 500 + código empresa; en mono-empresa la fila efectiva es "500". */
    public const NUMERADOR_BASE = 500;

    public const NUMERADOR_CLAVE = '500';

    public static function aplica(): bool
    {
        return EntornoEmpresaSupport::esInterforming();
    }

    public static function usaEsquemaCtamovReducido(): bool
    {
        return self::aplica();
    }

    /**
     * Clave del numerador de asientos.
     * Preferir 500+empresa si existe (multi); si no, "500" (Interforming actual).
     */
    public static function claveNumerador(int|string $codigoEmpresa = 0): string
    {
        $codigo = (int) preg_replace('/\D+/', '', (string) $codigoEmpresa);
        if ($codigo > 0) {
            $porEmpresa = (string) (self::NUMERADOR_BASE + $codigo);
            // Solo devolver 500+emp si no es la base sola malinterpretada; la resolución
            // con fallback a 500 está en leer/persistir al consultar Anita.
            return $porEmpresa;
        }

        return self::NUMERADOR_CLAVE;
    }

    public static function leerSiguienteCandidato(int|string $codigoEmpresa, ?string $pathSistema = null): int
    {
        $apiAnita = new ApiAnita();
        $clavePreferida = self::claveNumerador($codigoEmpresa);
        $clave = self::resolverClaveExistente($apiAnita, $clavePreferida, $pathSistema);

        $parsed = self::listarNumerador($apiAnita, $clave, $pathSistema);
        if ($parsed['error_lectura'] !== null || $parsed['filas'] === []) {
            throw new RuntimeException(
                'No se pudo leer numerador Anita Interforming (clave '.$clave.'): '
                .($parsed['error_lectura'] ?? 'sin filas')
            );
        }

        return (int) ($parsed['filas'][0]->num_ult_numero ?? 0) + 1;
    }

    public static function persistirNumerador(
        int|string $codigoEmpresa,
        int $numeroAsignado,
        ?string $pathSistema = null
    ): void {
        $apiAnita = new ApiAnita();
        $clave = self::resolverClaveExistente(
            $apiAnita,
            self::claveNumerador($codigoEmpresa),
            $pathSistema
        );

        $data = [
            'acc' => 'update',
            'tabla' => 'numerador',
            'sistema' => 'ventas',
            'valores' => " num_ult_numero = '".$numeroAsignado."' ",
            'whereArmado' => " WHERE num_clave='".str_replace("'", "''", $clave)."'",
        ];
        if (is_string($pathSistema) && trim($pathSistema) !== '') {
            $data['path_sistema'] = $pathSistema;
        }

        $apiAnita->apiCallEscritura($data, 'asiento_numerador_interforming_reservar');
    }

    /**
     * Si 500+emp no tiene fila (o es 501 con contador ajeno), usar "500"
     * cuando esa fila existe — es el contador vivo de asientos en Interforming.
     */
    private static function resolverClaveExistente(
        ApiAnita $apiAnita,
        string $clavePreferida,
        ?string $pathSistema
    ): string {
        if ($clavePreferida === self::NUMERADOR_CLAVE) {
            return self::NUMERADOR_CLAVE;
        }

        $preferida = self::listarNumerador($apiAnita, $clavePreferida, $pathSistema);
        $base = self::listarNumerador($apiAnita, self::NUMERADOR_CLAVE, $pathSistema);

        $ultPref = ($preferida['error_lectura'] === null && $preferida['filas'] !== [])
            ? (int) ($preferida['filas'][0]->num_ult_numero ?? 0)
            : -1;
        $ultBase = ($base['error_lectura'] === null && $base['filas'] !== [])
            ? (int) ($base['filas'][0]->num_ult_numero ?? 0)
            : -1;

        // Contador vivo = el mayor entre clave preferida y 500.
        if ($ultBase > $ultPref) {
            return self::NUMERADOR_CLAVE;
        }
        if ($ultPref >= 0) {
            return $clavePreferida;
        }
        if ($ultBase >= 0) {
            return self::NUMERADOR_CLAVE;
        }

        return $clavePreferida;
    }

    /**
     * @return array{filas: list<object>, error_lectura: ?string}
     */
    private static function listarNumerador(ApiAnita $apiAnita, string $clave, ?string $pathSistema): array
    {
        $data = [
            'acc' => 'list',
            'tabla' => 'numerador',
            'sistema' => 'ventas',
            'campos' => 'num_ult_numero',
            'whereArmado' => " WHERE num_clave='".str_replace("'", "''", $clave)."'",
        ];
        if (is_string($pathSistema) && trim($pathSistema) !== '') {
            $data['path_sistema'] = $pathSistema;
        }

        return ApiAnita::parsearRespuestaLista((string) $apiAnita->apiCall($data));
    }
}

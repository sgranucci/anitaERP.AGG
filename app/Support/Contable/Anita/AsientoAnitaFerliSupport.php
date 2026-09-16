<?php

declare(strict_types=1);

namespace App\Support\Contable\Anita;

use App\ApiAnita;
use App\Support\Configuracion\EntornoEmpresaSupport;
use RuntimeException;

/**
 * Contabilización de asientos Anita solo para Calzados Ferli (/usr2/ferli).
 *
 * Verificado 15/sep/2026 contra bridge Ferli:
 * - No hay tabla `numabm` usable (UNLOAD falla en shared/ventas/contab).
 * - El numerador de asientos es `ventas.numerador` clave 501 (alineado a MAX(ctamov)).
 * - `ctamov` no tiene ctav_o_compra ni ctav_*_umod (igual esquema reducido El Bierzo).
 *
 * AGG sigue en AsientoRepository con numabm a-ctamov.c y columnas completas.
 */
final class AsientoAnitaFerliSupport
{
    public const NUMERADOR_CLAVE = '501';

    public static function aplica(): bool
    {
        return EntornoEmpresaSupport::esFerli();
    }

    /**
     * true → INSERT ctamov sin columnas AGG (o_compra / umod).
     */
    public static function usaEsquemaCtamovReducido(): bool
    {
        return self::aplica();
    }

    public static function leerSiguienteCandidato(?string $pathSistema = null): int
    {
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'tabla' => 'numerador',
            'sistema' => 'ventas',
            'campos' => 'num_ult_numero',
            'whereArmado' => " WHERE num_clave='".self::NUMERADOR_CLAVE."'",
        ];
        if (is_string($pathSistema) && trim($pathSistema) !== '') {
            $data['path_sistema'] = $pathSistema;
        }

        $parsed = ApiAnita::parsearRespuestaLista((string) $apiAnita->apiCall($data));
        if ($parsed['error_lectura'] !== null || $parsed['filas'] === []) {
            throw new RuntimeException(
                'No se pudo leer numerador Anita Ferli (clave '.self::NUMERADOR_CLAVE.'): '
                .($parsed['error_lectura'] ?? 'sin filas')
            );
        }

        return (int) ($parsed['filas'][0]->num_ult_numero ?? 0) + 1;
    }

    public static function persistirNumerador(int $numeroAsignado, ?string $pathSistema = null): void
    {
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'update',
            'tabla' => 'numerador',
            'sistema' => 'ventas',
            'valores' => " num_ult_numero = '".$numeroAsignado."' ",
            'whereArmado' => " WHERE num_clave='".self::NUMERADOR_CLAVE."'",
        ];
        if (is_string($pathSistema) && trim($pathSistema) !== '') {
            $data['path_sistema'] = $pathSistema;
        }

        $apiAnita->apiCallEscritura($data, 'asiento_numerador_ferli_reservar');
    }
}

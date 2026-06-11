<?php

namespace App\Support\Caja\AnitaSync;

/**
 * Esquema Informix rendgastro (DDL en docs/rendgastro.sql, origen /home/sergio/tmp/rendgastro.sql).
 *
 * INSERT del bridge debe listar todas las columnas en este orden; numéricas sin mapeo ERP → 0.
 */
final class RendicionEstacionamientoRendgastroEsquema
{
    /**
     * float/integer del DDL que estacionamiento no mapea desde el ERP (INSERT siempre 0).
     * Incluye bloque ccig / ccig_caea / ccignc / ccignc_caea (entre rendg_nro_fac_caea y rendg_host).
     *
     * @var list<string>
     */
    public const COLUMNAS_NUMERICAS_SIN_MAPEO = [
        'rendg_ccig1',
        'rendg_ccig2',
        'rendg_ccig3',
        'rendg_ccig4',
        'rendg_ccig5',
        'rendg_ccig6',
        'rendg_ccig1_caea',
        'rendg_ccig2_caea',
        'rendg_ccig3_caea',
        'rendg_ccig4_caea',
        'rendg_ccig5_caea',
        'rendg_ccig6_caea',
        'rendg_ccignc1',
        'rendg_ccignc2',
        'rendg_ccignc3',
        'rendg_ccignc4',
        'rendg_ccignc5',
        'rendg_ccignc6',
        'rendg_ccignc1_caea',
        'rendg_ccignc2_caea',
        'rendg_ccignc3_caea',
        'rendg_ccignc4_caea',
        'rendg_ccignc5_caea',
        'rendg_ccignc6_caea',
    ];

    /**
     * @return list<array{columna: string, tipo: 'decimal', fijo_decimal: float}>
     */
    public static function definicionesDecimalCero(): array
    {
        $defs = [];
        foreach (self::columnasNumericasSinMapeoParaInsert() as $nombre) {
            $defs[] = [
                'columna' => $nombre,
                'tipo' => 'decimal',
                'fijo_decimal' => 0.0,
            ];
        }

        return $defs;
    }

    /**
     * @return list<string>
     */
    public static function columnasNumericasSinMapeoParaInsert(): array
    {
        $extra = config('rendicion_estacionamiento_anita.cabecera_campos_numericos_insert_cero');
        if (! is_array($extra) || $extra === []) {
            return self::COLUMNAS_NUMERICAS_SIN_MAPEO;
        }

        return array_values(array_unique(array_merge(
            self::COLUMNAS_NUMERICAS_SIN_MAPEO,
            $extra,
        )));
    }
}

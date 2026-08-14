<?php

namespace App\Support\Contable;

/**
 * Tipos de concepto del cierre bingo (legacy concbingo.concb_tipo_conc).
 * A partir de ago/2026 el catálogo operativo vive en bingo_concepto_rendicion (ERP).
 */
final class CierreRendicionBingoConceptoTipos
{
    public const BINGO = '0';

    public const PREMIO = '1';

    public const PORC_RECAUD = '2';

    public const PANTALLA = '3';

    public const ULT_BOLA = '4';

    public const PORC_POZO = '5';

    public const PAGO = '6';

    /**
     * Código ERP (bingo_concepto_rendicion.codigo) → tipo de columna p-vtabingo.
     *
     * @var array<string, string>
     */
    public const TIPO_POR_CODIGO_ERP = [
        'BINGO47' => self::BINGO,
        'LINEA6' => self::PREMIO,
        'PANTALLAS' => self::PANTALLA,
        'PREMEFEC' => self::PANTALLA,
        'BUB_APE' => self::ULT_BOLA,
        'BUB_CIE' => self::ULT_BOLA,
        'PREM2' => self::PORC_POZO,
        'PREM5' => self::PORC_POZO,
        'PREM10' => self::PORC_POZO,
        'PREM15' => self::PORC_POZO,
        'PREM65' => self::PORC_POZO,
    ];

    /**
     * Canones / % recaudación del listado p-vtabingo que no se cargan en la rendición
     * (solo columnas de reporte / asiento de canones).
     *
     * @return list<array{
     *   concepto: int,
     *   desc: string,
     *   tipo_conc: string,
     *   porcentaje: float,
     *   cta_contable_codigo: int,
     *   contrapartida_codigo: int
     * }>
     */
    public static function extrasCatalogoReporte(): array
    {
        return [
            [
                'concepto' => 3,
                'desc' => 'Premio 5% recaudacion',
                'tipo_conc' => self::PORC_RECAUD,
                'porcentaje' => 5.0,
                'cta_contable_codigo' => 0,
                'contrapartida_codigo' => 0,
            ],
            [
                'concepto' => 100,
                'desc' => 'Municipalidad 4%',
                'tipo_conc' => self::PAGO,
                'porcentaje' => 4.0,
                'cta_contable_codigo' => 521020003,
                'contrapartida_codigo' => 214010007,
            ],
            [
                'concepto' => 110,
                'desc' => 'Loteria 17%',
                'tipo_conc' => self::PAGO,
                'porcentaje' => 17.0,
                'cta_contable_codigo' => 521020001,
                'contrapartida_codigo' => 215010001,
            ],
        ];
    }

    public static function tipoPorCodigoErp(string $codigo): ?string
    {
        $codigo = strtoupper(trim($codigo));

        return self::TIPO_POR_CODIGO_ERP[$codigo] ?? null;
    }
}

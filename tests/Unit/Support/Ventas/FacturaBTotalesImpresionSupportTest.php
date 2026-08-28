<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\FacturaBTotalesImpresionSupport as S;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD): qué líneas del pie se imprimen en factura B.
 */
final class FacturaBTotalesImpresionSupportTest extends TestCase
{
    public function test_renombra_percepcion_no_categorizado_a_percepcion_iva(): void
    {
        $fila = [
            'concepto' => 'Percepcion no categorizado 10.5%',
            'tasa' => 10.5,
            'importe' => 3783.07,
        ];

        self::assertTrue(S::esPercepcionNoCategorizado($fila));
        self::assertTrue(S::mostrar($fila));
        self::assertSame(S::ETIQUETA_PERCEPCION_IVA, S::etiqueta($fila));
    }

    public function test_renombra_percepcion_iibb_caba(): void
    {
        $porNombre = [
            'concepto' => 'Perc. Capital Federal 6.000000%',
            'tasa' => 6,
            'importe' => 180.50,
        ];
        $porJurisdiccion = [
            'concepto' => 'Perc. CABA 6%',
            'tasa' => 6,
            'importe' => 90,
            'jurisdiccion' => 901,
        ];

        self::assertTrue(S::esPercepcionIibbCaba($porNombre));
        self::assertTrue(S::esPercepcionIibbCaba($porJurisdiccion));
        self::assertSame(S::ETIQUETA_PERCEPCION_IIBB_CABA, S::etiqueta($porNombre));
    }

    public function test_no_confunde_iibb_buenos_aires_ni_perc_iva_ri(): void
    {
        self::assertFalse(S::mostrar([
            'concepto' => 'Perc. BUENOS AIRES 3.5%',
            'tasa' => 3.5,
            'importe' => 50,
        ]));
        self::assertFalse(S::mostrar([
            'concepto' => 'Percepcion IVA 3%',
            'tasa' => 3,
            'importe' => 30,
        ]));
        self::assertFalse(S::mostrar([
            'concepto' => 'Iva 21%',
            'tasa' => 21,
            'importe' => 210,
        ]));
    }

    public function test_omite_percepciones_en_cero_y_siempre_muestra_el_total(): void
    {
        self::assertFalse(S::mostrar([
            'concepto' => 'Percepcion no categorizado 10.5%',
            'tasa' => 10.5,
            'importe' => 0,
        ]));
        self::assertTrue(S::mostrar([
            'concepto' => 'Total',
            'tasa' => 0,
            'importe' => 0,
        ]));
    }

    public function test_lineas_solo_percepciones_pedidas_y_total(): void
    {
        $lineas = S::lineas([
            ['concepto' => 'Gravado al 21%', 'tasa' => 21, 'importe' => 1000],
            ['concepto' => 'Iva 21%', 'tasa' => 21, 'importe' => 210],
            ['concepto' => 'Perc. CAPITAL FEDERAL 6%', 'tasa' => 6, 'importe' => 60],
            ['concepto' => 'Percepcion no categorizado 10.5%', 'tasa' => 10.5, 'importe' => 133.05],
            ['concepto' => 'Total', 'tasa' => 0, 'importe' => 1403.05],
        ]);

        self::assertSame([
            [
                'concepto' => S::ETIQUETA_PERCEPCION_IIBB_CABA,
                'tasa' => 6.0,
                'importe' => 60.0,
                'es_total' => false,
            ],
            [
                'concepto' => S::ETIQUETA_PERCEPCION_IVA,
                'tasa' => 10.5,
                'importe' => 133.05,
                'es_total' => false,
            ],
            [
                'concepto' => 'Total',
                'tasa' => 0.0,
                'importe' => 1403.05,
                'es_total' => true,
            ],
        ], $lineas);
    }
}

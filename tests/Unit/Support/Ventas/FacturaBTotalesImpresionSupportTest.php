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

    /**
     * Venta 1139 de El Bierzo (29/10/2026). ImpuestoService guarda «Descuento Gral.»
     * ya sobre bruto (a-comprob: tot_dto × 1,21) y el gravado neteado con el dto neto.
     * El pie no puede volver a multiplicarlo.
     */
    public function test_no_vuelve_a_sumar_iva_al_descuento_de_cabecera(): void
    {
        $lineas = S::lineas([
            ['concepto' => 'Subtotal', 'tasa' => 0, 'importe' => 9519.99],
            ['concepto' => 'Descuento Gral. 20%', 'tasa' => 20, 'importe' => -2303.84],
            ['concepto' => 'Gravado al 21.000%', 'tasa' => 21, 'importe' => 7615.99],
            ['concepto' => 'Iva 21.000%', 'tasa' => 21, 'importe' => 1599.36],
            ['concepto' => 'Percepcion no categorizado 10.5%', 'tasa' => 10.5, 'importe' => 967.61],
            ['concepto' => 'Total', 'tasa' => 0, 'importe' => 10182.96],
        ]);

        self::assertSame([
            // 9519,99 × 1,21: misma base que el renglón impreso.
            ['concepto' => 'Subtotal', 'tasa' => 0.0, 'importe' => 11519.19, 'es_total' => false],
            ['concepto' => 'Descuento Gral. 20%', 'tasa' => 20.0, 'importe' => -2303.84, 'es_total' => false],
            ['concepto' => S::ETIQUETA_PERCEPCION_IVA, 'tasa' => 10.5, 'importe' => 967.61, 'es_total' => false],
            ['concepto' => 'Total', 'tasa' => 0.0, 'importe' => 10182.96, 'es_total' => true],
        ], $lineas);
        self::assertSame(0.0, S::descuadre($lineas));
    }

    public function test_el_pie_cierra_con_exento_impuesto_interno_logistica_y_abasto(): void
    {
        $lineas = S::lineas([
            ['concepto' => 'Subtotal', 'tasa' => 0, 'importe' => 11000.0],
            ['concepto' => 'Descuento Gral. 10%', 'tasa' => 10, 'importe' => -1189.0],
            ['concepto' => 'Exento', 'tasa' => 0, 'importe' => 1000.0],
            ['concepto' => 'Total Logistica', 'tasa' => 0, 'importe' => 300.0],
            ['concepto' => 'Gravado al 21%', 'tasa' => 21, 'importe' => 9000.0],
            ['concepto' => 'Iva 21%', 'tasa' => 21, 'importe' => 1890.0],
            ['concepto' => 'Impuesto Interno', 'tasa' => 0, 'importe' => 150.0],
            ['concepto' => 'Total Abasto', 'tasa' => 1.5, 'importe' => 80.0],
            ['concepto' => 'Percepcion no categorizado 10.5%', 'tasa' => 10.5, 'importe' => 1304.10],
            ['concepto' => 'Total', 'tasa' => 0, 'importe' => 13724.10],
        ]);

        self::assertSame(0.0, S::descuadre($lineas));
        self::assertSame('Subtotal', $lineas[0]['concepto']);
        self::assertSame(13609.0, $lineas[0]['importe']);
    }

    public function test_el_pie_cierra_con_dos_alicuotas_de_iva(): void
    {
        $lineas = S::lineas([
            ['concepto' => 'Subtotal', 'tasa' => 0, 'importe' => 2000.0],
            ['concepto' => 'Gravado al 21%', 'tasa' => 21, 'importe' => 1000.0],
            ['concepto' => 'Iva 21%', 'tasa' => 21, 'importe' => 210.0],
            ['concepto' => 'Gravado al 10.5%', 'tasa' => 10.5, 'importe' => 1000.0],
            ['concepto' => 'Iva 10.5%', 'tasa' => 10.5, 'importe' => 105.0],
            ['concepto' => 'Total', 'tasa' => 0, 'importe' => 2315.0],
        ]);

        self::assertSame(0.0, S::descuadre($lineas));
        self::assertSame(2315.0, $lineas[0]['importe']);
    }

    public function test_el_pie_cierra_en_nota_de_credito_letra_b(): void
    {
        $lineas = S::lineas([
            ['concepto' => 'Subtotal', 'tasa' => 0, 'importe' => -9519.99],
            ['concepto' => 'Descuento Gral. 20%', 'tasa' => 20, 'importe' => 2303.84],
            ['concepto' => 'Gravado al 21%', 'tasa' => 21, 'importe' => -7615.99],
            ['concepto' => 'Iva 21%', 'tasa' => 21, 'importe' => -1599.36],
            ['concepto' => 'Percepcion no categorizado 10.5%', 'tasa' => 10.5, 'importe' => -967.61],
            ['concepto' => 'Total', 'tasa' => 0, 'importe' => -10182.96],
        ]);

        self::assertSame(0.0, S::descuadre($lineas));
        self::assertSame(-11519.19, $lineas[0]['importe']);
    }

    public function test_el_pie_cierra_sin_descuento(): void
    {
        $lineas = S::lineas([
            ['concepto' => 'Subtotal', 'tasa' => 0, 'importe' => 1000.0],
            ['concepto' => 'Gravado al 21%', 'tasa' => 21, 'importe' => 1000.0],
            ['concepto' => 'Iva 21%', 'tasa' => 21, 'importe' => 210.0],
            ['concepto' => 'Perc. Capital Federal 6%', 'tasa' => 6, 'importe' => 60.0, 'jurisdiccion' => 901],
            ['concepto' => 'Total', 'tasa' => 0, 'importe' => 1270.0],
        ]);

        self::assertSame(0.0, S::descuadre($lineas));
        self::assertSame(1210.0, $lineas[0]['importe']);
    }

    public function test_descuento_por_importe_fijo_no_descuadra_el_pie(): void
    {
        $lineas = S::lineas([
            ['concepto' => 'Subtotal', 'tasa' => 0, 'importe' => 1000.0],
            ['concepto' => 'Gravado al 21%', 'tasa' => 21, 'importe' => 1000.0],
            ['concepto' => 'Iva 21%', 'tasa' => 21, 'importe' => 210.0],
            ['concepto' => 'Descuento por importe', 'tasa' => 0, 'importe' => -100.0],
            ['concepto' => 'Total', 'tasa' => 0, 'importe' => 1110.0],
        ]);

        self::assertSame(0.0, S::descuadre($lineas));
        self::assertSame(1210.0, $lineas[0]['importe']);
    }

    public function test_precio_b_incluye_iva_cuando_el_precio_es_neto(): void
    {
        self::assertSame(18016.97, \App\Support\Ventas\FacturaLineaPrecioImpresionSupport::conIva(14890.06, 'N', 21));
        self::assertSame(14890.06, \App\Support\Ventas\FacturaLineaPrecioImpresionSupport::conIva(14890.06, 'S', 21));
    }
}

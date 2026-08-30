<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\NotaCreditoPercepcionIibbSupport as S;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD): prorrateo IIBB en NC de mostrador administración.
 */
class NotaCreditoPercepcionIibbSupportTest extends TestCase
{
    protected function tearDown(): void
    {
        S::olvidarCache();
        parent::tearDown();
    }

    public function test_agg_mostrador_admin_prorratea(): void
    {
        $out = S::paraNotaCredito([
            S::FLAG_ES_NC => true,
            S::FLAG_VENTA_ORIGEN => 10,
            'percepciones_iibb_origen' => [[
                'concepto' => 'Perc. Buenos Aires 2.5%',
                'tasa' => 2.5,
                'baseimponible' => 12000,
                'provincia_id' => 1,
                'jurisdiccion' => 902,
                'importe' => 300,
            ]],
        ], 2000.0);

        self::assertNotNull($out);
        self::assertCount(1, $out);
        self::assertSame(50.0, $out[0]['importe']);
        self::assertSame(2.5, $out[0]['tasa']);
    }

    public function test_pos_no_anexa_origen(): void
    {
        $datos = ['id' => 1];
        S::anexarOrigenSiCorresponde($datos, [
            'tipotransaccion_signo' => 'R',
            'venta_id' => 99,
        ], false);

        self::assertArrayNotHasKey(S::FLAG_ES_NC, $datos);
        self::assertArrayNotHasKey(S::FLAG_VENTA_ORIGEN, $datos);
    }

    public function test_mostrador_admin_anexa_origen(): void
    {
        $datos = ['id' => 1];
        S::anexarOrigenSiCorresponde($datos, [
            'tipotransaccion_signo' => 'R',
            'venta_id' => 99,
        ], true);

        self::assertTrue($datos[S::FLAG_ES_NC]);
        self::assertSame(99, $datos[S::FLAG_VENTA_ORIGEN]);
    }

    public function test_no_aplica_a_factura_aunque_haya_origen(): void
    {
        self::assertNull(S::paraNotaCredito([
            S::FLAG_VENTA_ORIGEN => 10,
            'percepciones_iibb_origen' => [[
                'concepto' => 'Perc. CABA 3%',
                'tasa' => 3,
                'baseimponible' => 12000,
                'provincia_id' => 1,
                'importe' => 360,
            ]],
        ], 12000.0));
    }

    public function test_nc_total_copia_importes_de_la_factura(): void
    {
        $filas = [[
            'concepto' => 'Perc. CABA 3%',
            'tasa' => 3.0,
            'baseimponible' => 12000.0,
            'provincia_id' => 2,
            'jurisdiccion' => 901,
            'importe' => 360.0,
        ]];

        $out = S::prorratearFilas($filas, 12000.0, 12000.0);

        self::assertCount(1, $out);
        self::assertSame(360.0, $out[0]['importe']);
        self::assertSame(12000.0, $out[0]['baseimponible']);
        self::assertSame(3.0, $out[0]['tasa']);
        self::assertSame('Perc. CABA 3%', $out[0]['concepto']);
    }

    public function test_nc_parcial_prorratea_aunque_quede_bajo_el_minimo(): void
    {
        $filas = [[
            'concepto' => 'Perc. CABA 3%',
            'tasa' => 3.0,
            'baseimponible' => 12000.0,
            'provincia_id' => 2,
            'jurisdiccion' => 901,
            'importe' => 360.0,
        ]];

        $out = S::prorratearFilas($filas, 2000.0, 12000.0);

        self::assertCount(1, $out);
        self::assertSame(60.0, $out[0]['importe']);
        self::assertSame(2000.0, $out[0]['baseimponible']);
        self::assertSame(3.0, $out[0]['tasa']);
    }

    public function test_factura_sin_percepcion_no_inventa(): void
    {
        self::assertSame([], S::prorratearFilas([], 2000.0, 12000.0));
    }

    public function test_no_confunde_piva_ni_no_categorizado(): void
    {
        $filas = S::extraerFilasIibb([
            ['concepto' => 'Percepcion IVA 3%', 'importe' => 30, 'tasa' => 3, 'baseimponible' => 1000],
            ['concepto' => 'Percepcion no categorizado 10.5%', 'importe' => 100, 'tasa' => 10.5, 'baseimponible' => 1000],
            ['concepto' => 'Perc. Buenos Aires 2.5%', 'importe' => 50, 'tasa' => 2.5, 'baseimponible' => 2000, 'provincia_id' => 1],
        ]);

        self::assertCount(1, $filas);
        self::assertSame('Perc. Buenos Aires 2.5%', $filas[0]['concepto']);
    }

    public function test_factor_no_supera_uno(): void
    {
        self::assertSame(1.0, S::factor(15000.0, 12000.0));
        self::assertEqualsWithDelta(1 / 6, S::factor(2000.0, 12000.0), 0.0000001);
        self::assertSame(0.0, S::factor(0.0, 12000.0));
    }

    public function test_ncp_sin_percepcion_no_prorratea_ni_recalcula(): void
    {
        $out = S::paraNotaCredito([
            S::FLAG_NCP => true,
            'nc_percepcion_iibb' => false,
            S::FLAG_ES_NC => true,
            S::FLAG_VENTA_ORIGEN => 10,
            'percepciones_iibb_origen' => [[
                'concepto' => 'Perc. CABA 3%',
                'tasa' => 3,
                'baseimponible' => 12000,
                'provincia_id' => 2,
                'importe' => 360,
            ]],
        ], 2000.0);

        self::assertSame([], $out);
    }

    public function test_ncp_con_percepcion_habilitada_prorratea(): void
    {
        $out = S::paraNotaCredito([
            S::FLAG_NCP => true,
            'nc_percepcion_iibb' => true,
            S::FLAG_ES_NC => true,
            S::FLAG_VENTA_ORIGEN => 10,
            'percepciones_iibb_origen' => [[
                'concepto' => 'Perc. CABA 3%',
                'tasa' => 3,
                'baseimponible' => 12000,
                'provincia_id' => 2,
                'importe' => 360,
            ]],
        ], 2000.0);

        self::assertNotNull($out);
        self::assertCount(1, $out);
        self::assertSame(60.0, $out[0]['importe']);
    }

    public function test_mostrador_anexa_flag_ncp(): void
    {
        $datos = ['id' => 1];
        S::anexarOrigenSiCorresponde($datos, [
            'tipotransaccion_signo' => 'R',
            'venta_id' => 99,
            S::FLAG_NCP => true,
        ], true);

        self::assertTrue($datos[S::FLAG_NCP]);
        self::assertTrue($datos[S::FLAG_ES_NC]);
    }
}

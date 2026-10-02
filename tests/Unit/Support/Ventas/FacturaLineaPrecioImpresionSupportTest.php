<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\FacturaLineaPrecioImpresionSupport as S;
use PHPUnit\Framework\TestCase;

/**
 * El renglón de la factura A tiene que imprimir el mismo neto que el subtotal.
 */
final class FacturaLineaPrecioImpresionSupportTest extends TestCase
{
    public function test_rebisco_netea_iva_y_percepcion_al_subtotal(): void
    {
        $neto = S::netear(10330794.26, 10330794.26, 'S', 21.0, 4.0);

        self::assertSame(8264635.41, round($neto['precio'], 2));
        self::assertSame(8264635.41, round($neto['preciosindescuento'], 2));
    }

    public function test_kandiko_y_biyemas_coinciden_con_el_subtotal(): void
    {
        $kandiko = S::netear(7563617.22, 7563617.22, 'S', 21.0, 4.0);
        $biyemas = S::netear(17156497.61, 17156497.61, 'S', 21.0, 4.0);

        self::assertSame(6050893.78, round($kandiko['precio'], 2));
        self::assertSame(13725198.09, round($biyemas['precio'], 2));
    }

    public function test_sin_percepcion_solo_divide_por_el_iva(): void
    {
        $neto = S::netear(2167500.0, 2167500.0, 'S', 21.0, 0.0);

        self::assertSame(1791322.31, round($neto['precio'], 2));
    }

    public function test_precio_ya_neto_no_se_vuelve_a_dividir(): void
    {
        $neto = S::netear(1000.0, 1200.0, '2', 21.0, 4.0);

        self::assertSame(1000.0, $neto['precio']);
        self::assertSame(1200.0, $neto['preciosindescuento']);
    }

    public function test_tasa_detraccion_suma_solo_percepciones_del_divisor(): void
    {
        $conceptos = [
            ['concepto' => 'Subtotal', 'tasa' => 0],
            ['concepto' => 'Gravado al 21.000%', 'tasa' => 21],
            ['concepto' => 'Iva 21.000%', 'tasa' => 21],
            ['concepto' => 'Perc. Buenos Aires 4%', 'tasa' => 4],
            ['concepto' => 'Percepcion no categorizado 10.5%', 'tasa' => 10.5],
            ['concepto' => 'Total', 'tasa' => 0],
        ];

        self::assertSame(4.0, S::tasaDetraccion($conceptos, true));
        self::assertSame(0.0, S::tasaDetraccion($conceptos, false));
    }
}

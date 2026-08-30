<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\FacturaAsientoDescuentoPieSupport as S;
use Tests\TestCase;

/**
 * Test puro: no toca BD.
 */
class FacturaAsientoDescuentoPieSupportTest extends TestCase
{
    public function test_importe_descuento_desde_conceptos(): void
    {
        $conceptos = [
            ['concepto' => 'Subtotal', 'importe' => 1592313.43],
            ['concepto' => 'Descuento Gral. 6%', 'importe' => -95538.81],
            ['concepto' => 'Gravado al 21.000%', 'importe' => 1496774.61],
            ['concepto' => 'Iva 21.000%', 'importe' => 314322.67],
            ['concepto' => 'Total', 'importe' => 1829058.58],
        ];

        self::assertEqualsWithDelta(95538.81, S::importeDesdeConceptos($conceptos), 0.001);
        self::assertEqualsWithDelta(1496774.61, S::netoVentaFiscal($conceptos), 0.001);
    }

    public function test_no_confunde_no_gravado_con_gravado(): void
    {
        $conceptos = [
            ['concepto' => 'No Gravado', 'importe' => 100],
            ['concepto' => 'Gravado al 21.000%', 'importe' => 200],
            ['concepto' => 'Exento', 'importe' => 50],
        ];

        self::assertEqualsWithDelta(350.0, S::netoVentaFiscal($conceptos), 0.001);
    }

    public function test_netea_una_cuenta_de_venta_al_gravado(): void
    {
        $lineas = [
            ['empresa_id' => 1, 'cuentacontable_id' => 10, 'monto' => 1592313.43],
        ];
        $conceptos = [
            ['concepto' => 'Descuento Gral. 6%', 'importe' => -95538.81],
            ['concepto' => 'Gravado al 21.000%', 'importe' => 1496774.61],
        ];

        $out = S::netearLineasVenta($lineas, $conceptos);

        self::assertEqualsWithDelta(1496774.61, $out[0]['monto'], 0.001);
    }

    public function test_prorratea_varias_cuentas_y_cierra_centavos(): void
    {
        $lineas = [
            ['empresa_id' => 1, 'cuentacontable_id' => 10, 'monto' => 100.00],
            ['empresa_id' => 1, 'cuentacontable_id' => 11, 'monto' => 50.00],
        ];
        $conceptos = [
            ['concepto' => 'Descuento Gral. 10%', 'importe' => -15.00],
            ['concepto' => 'Gravado al 21.000%', 'importe' => 135.00],
        ];

        $out = S::netearLineasVenta($lineas, $conceptos);

        self::assertEqualsWithDelta(90.00, $out[0]['monto'], 0.001);
        self::assertEqualsWithDelta(45.00, $out[1]['monto'], 0.001);
        self::assertEqualsWithDelta(135.00, $out[0]['monto'] + $out[1]['monto'], 0.001);
    }

    public function test_no_vuelve_a_descontar_si_venta_ya_es_el_gravado(): void
    {
        $lineas = [
            ['empresa_id' => 1, 'cuentacontable_id' => 10, 'monto' => 1496774.61],
        ];
        $conceptos = [
            ['concepto' => 'Descuento Gral. 6%', 'importe' => -95538.81],
            ['concepto' => 'Gravado al 21.000%', 'importe' => 1496774.61],
        ];

        $out = S::netearLineasVenta($lineas, $conceptos);

        self::assertEqualsWithDelta(1496774.61, $out[0]['monto'], 0.001);
    }

    public function test_factura_b_puccio_netea_al_gravado(): void
    {
        $lineas = [
            ['empresa_id' => 1, 'cuentacontable_id' => 10, 'monto' => 20552.08],
        ];
        $conceptos = [
            ['concepto' => 'Descuento Gral. 20%', 'importe' => -4110.41],
            ['concepto' => 'Gravado al 21.000%', 'importe' => 16441.66],
        ];

        $out = S::netearLineasVenta($lineas, $conceptos);

        self::assertEqualsWithDelta(16441.66, $out[0]['monto'], 0.001);
    }
}

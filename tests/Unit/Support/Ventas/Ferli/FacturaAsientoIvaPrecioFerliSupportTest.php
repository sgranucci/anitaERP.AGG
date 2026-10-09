<?php

namespace Tests\Unit\Support\Ventas\Ferli;

use App\Support\Ventas\Ferli\FacturaAsientoIvaPrecioFerliSupport as S;
use Tests\TestCase;

/**
 * Test puro: no toca BD.
 */
class FacturaAsientoIvaPrecioFerliSupportTest extends TestCase
{
    public function test_asiento_igual_al_total_pasa_con_precio_con_iva(): void
    {
        $conceptos = [
            ['concepto' => 'Gravado al 21%', 'importe' => 100.00],
            ['concepto' => 'Iva 21%', 'importe' => 21.00],
            ['concepto' => 'Total', 'importe' => 121.00],
        ];
        $asiento = [
            ['monto' => 100.00],
            ['monto' => 21.00],
        ];

        self::assertNull(S::mensajeSiNoCierra($conceptos, $asiento));
    }

    public function test_asiento_igual_al_total_pasa_con_precio_sin_iva(): void
    {
        $conceptos = [
            ['concepto' => 'Gravado al 21%', 'importe' => 100.00],
            ['concepto' => 'Iva 21%', 'importe' => 21.00],
            ['concepto' => 'Total', 'importe' => 121.00],
        ];
        $asiento = [
            ['monto' => 100.00],
            ['monto' => 21.00],
        ];

        self::assertNull(S::mensajeSiNoCierra($conceptos, $asiento));
    }

    public function test_rechaza_asiento_mayor_que_el_total(): void
    {
        $conceptos = [
            ['concepto' => 'Gravado al 21%', 'importe' => 100.00],
            ['concepto' => 'Iva 21%', 'importe' => 21.00],
            ['concepto' => 'Total', 'importe' => 121.00],
        ];
        $asiento = [
            ['monto' => 121.00],
            ['monto' => 21.00],
        ];

        $mensaje = S::mensajeSiNoCierra($conceptos, $asiento);

        self::assertNotNull($mensaje);
        self::assertStringContainsString('supera el total', $mensaje);
    }

    public function test_un_centavo_no_corta(): void
    {
        $conceptos = [
            ['concepto' => 'Total', 'importe' => 121.00],
        ];
        $asiento = [
            ['monto' => 121.02],
        ];

        self::assertNull(S::mensajeSiNoCierra($conceptos, $asiento));
    }

    public function test_asiento_menor_que_el_total_no_es_esta_regla(): void
    {
        $conceptos = [
            ['concepto' => 'Total', 'importe' => 121.00],
        ];
        $asiento = [
            ['monto' => 100.00],
        ];

        self::assertNull(S::mensajeSiNoCierra($conceptos, $asiento));
    }
}

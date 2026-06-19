<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\StkmaePrecioCompraAnitaBridgeSupport;
use PHPUnit\Framework\TestCase;

class StkmaePrecioCompraAnitaBridgeSupportTest extends TestCase
{
    public function test_calcular_push_desplaza_precios_y_graba_ultimo_en_compra3(): void
    {
        $resultado = StkmaePrecioCompraAnitaBridgeSupport::calcularPushPrecioCompra(
            [
                'stkm_pre_compra1' => 10.0,
                'stkm_pre_compra2' => 20.0,
                'stkm_pre_compra3' => 30.0,
                'stkm_cant_compra1' => 1.0,
                'stkm_cant_compra2' => 2.0,
                'stkm_cant_compra3' => 3.0,
                'stkm_ppp' => 25.0,
            ],
            45.5,
            7.0,
            20260619,
            0.0,
        );

        $this->assertSame(20.0, $resultado['stkm_pre_compra1']);
        $this->assertSame(30.0, $resultado['stkm_pre_compra2']);
        $this->assertSame(45.5, $resultado['stkm_pre_compra3']);
        $this->assertSame(2.0, $resultado['stkm_cant_compra1']);
        $this->assertSame(3.0, $resultado['stkm_cant_compra2']);
        $this->assertSame(7.0, $resultado['stkm_cant_compra3']);
        $this->assertSame(20260619, $resultado['stkm_fe_ult_compra']);
        $this->assertSame(45.5, $resultado['stkm_ppp']);
    }

    public function test_calcular_push_desplaza_monedas_compra_cuando_existen(): void
    {
        $resultado = StkmaePrecioCompraAnitaBridgeSupport::calcularPushPrecioCompra(
            [
                'stkm_pre_compra1' => 0,
                'stkm_pre_compra2' => 0,
                'stkm_pre_compra3' => 0,
                'stkm_cant_compra1' => 0,
                'stkm_cant_compra2' => 0,
                'stkm_cant_compra3' => 0,
                'stkm_ppp' => 0,
                'stkm_cod_mon_co1' => '1',
                'stkm_cod_mon_co2' => '2',
                'stkm_cod_mon_co3' => '3',
            ],
            100.0,
            5.0,
            20260101,
        );

        $this->assertSame('2', $resultado['stkm_cod_mon_co1']);
        $this->assertSame('3', $resultado['stkm_cod_mon_co2']);
        $this->assertSame('1', $resultado['stkm_cod_mon_co3']);
    }
}

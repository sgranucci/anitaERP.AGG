<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\RendicionMaquina\RendicionMaquinaValoresCuentacajaSupport;
use PHPUnit\Framework\TestCase;

class RendicionMaquinaEfectivoPesosCompletoTest extends TestCase
{
    public function test_reconoce_caja_pesos_y_excluye_qr_mep(): void
    {
        $this->assertTrue(RendicionMaquinaValoresCuentacajaSupport::esLineaEfectivoPesos([
            'codigo' => '300',
            'nombre' => 'CAJA PESOS REBISCO',
            'moneda_id' => 1,
        ]));
        $this->assertFalse(RendicionMaquinaValoresCuentacajaSupport::esLineaEfectivoPesos([
            'codigo' => '11301011',
            'nombre' => 'TOTAL COIN MAQUINAS',
            'moneda_id' => 1,
        ]));
        $this->assertFalse(RendicionMaquinaValoresCuentacajaSupport::esLineaEfectivoPesos([
            'codigo' => 'MMEP',
            'nombre' => 'MEP Maquinas',
            'moneda_id' => 1,
        ]));
    }

    public function test_no_suma_si_drop_es_menor_que_remesa(): void
    {
        $lineas = [[
            'codigo' => '300',
            'nombre' => 'CAJA PESOS REBISCO',
            'moneda_id' => 1,
            'monto' => 13740.0,
        ]];
        $out = RendicionMaquinaValoresCuentacajaSupport::aplicarExtraDropMenosRemesa(
            $lineas,
            115798200.0,
            174054900.0
        );
        $this->assertSame(13740.0, $out[0]['monto']);
    }

    public function test_rebisco_23_8_suma_drop_bruto_maniana_menos_remesa(): void
    {
        $lineas = [
            ['codigo' => '300', 'nombre' => 'CAJA PESOS REBISCO', 'moneda_id' => 1, 'monto' => 13740.0],
            ['codigo' => '11301011', 'nombre' => 'TOTAL COIN MAQUINAS', 'moneda_id' => 1, 'monto' => 48242811.43],
        ];
        $dropBrutoManiana = 179929700.0;
        $remesa = 174054900.0;
        $extra = round($dropBrutoManiana - $remesa, 2);
        $out = RendicionMaquinaValoresCuentacajaSupport::aplicarExtraDropMenosRemesa(
            $lineas,
            $dropBrutoManiana,
            $remesa
        );
        $this->assertEqualsWithDelta(13740.0 + $extra, $out[0]['monto'], 0.01);
        $this->assertSame(5888540.0, $out[0]['monto']);
        $this->assertSame(48242811.43, $out[1]['monto']);
    }

    public function test_suma_diferencia_positiva_a_caja_pesos(): void
    {
        $lineas = [
            ['codigo' => '300', 'nombre' => 'CAJA PESOS REBISCO', 'moneda_id' => 1, 'monto' => 13740.0],
            ['codigo' => '11301011', 'nombre' => 'TOTAL COIN MAQUINAS', 'moneda_id' => 1, 'monto' => 48242811.43],
        ];
        $extra = round(178248157.58 - 159596900.0, 2);
        $out = RendicionMaquinaValoresCuentacajaSupport::aplicarExtraDropMenosRemesa(
            $lineas,
            178248157.58,
            159596900.0
        );
        $this->assertEqualsWithDelta(13740.0 + $extra, $out[0]['monto'], 0.01);
        $this->assertSame(48242811.43, $out[1]['monto']);
    }
}

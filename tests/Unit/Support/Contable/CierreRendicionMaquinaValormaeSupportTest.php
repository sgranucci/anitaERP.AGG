<?php

namespace Tests\Unit\Support\Contable;

use App\Models\Caja\Cuentacaja;
use App\Support\Contable\CierreRendicionMaquinaValormaeSupport;
use Tests\TestCase;

class CierreRendicionMaquinaValormaeSupportTest extends TestCase
{
    public function test_codigo_valormae_25_es_slot_totalcoin_no_cuenta_financiera(): void
    {
        $r = CierreRendicionMaquinaValormaeSupport::resolver(25, null);

        $this->assertSame(25, $r['codigo']);
        $this->assertTrue(CierreRendicionMaquinaValormaeSupport::esTotalcoinSlot($r['codigo']));
        $this->assertFalse(CierreRendicionMaquinaValormaeSupport::esCuentaFinanciera($r['codigo'], $r['tipo']));
    }

    public function test_caja_pesos_codigo_100_no_se_toma_como_valormae_100(): void
    {
        $caja = new Cuentacaja([
            'codigo' => '100',
            'nombre' => 'CAJA PESOS BIYEMAS',
            'descripcion_operaciones' => 'Efectivo pesos',
        ]);

        $r = CierreRendicionMaquinaValormaeSupport::resolver(null, $caja);

        $this->assertSame(1, $r['codigo']);
        $this->assertSame(CierreRendicionMaquinaValormaeSupport::TIPO_EFE_PESOS, $r['tipo']);
        $this->assertFalse(CierreRendicionMaquinaValormaeSupport::esTotalcoinSlot($r['codigo']));
    }

    public function test_totalcoin_maq_va_a_cuenta_financiera(): void
    {
        $caja = new Cuentacaja([
            'codigo' => '11301011',
            'nombre' => 'TOTAL COIN MAQUINAS',
            'descripcion_operaciones' => 'TotalCoin QR Maquina',
        ]);

        $r = CierreRendicionMaquinaValormaeSupport::resolver(null, $caja);

        $this->assertSame(21, $r['codigo']);
        $this->assertTrue(CierreRendicionMaquinaValormaeSupport::esCuentaFinanciera($r['codigo'], $r['tipo']));
    }

    public function test_banco_macro_es_varios_cuenta_financiera(): void
    {
        $caja = new Cuentacaja([
            'codigo' => '1112',
            'nombre' => 'BANCO MACRO NUEVAS EX ITAU BSA',
            'descripcion_operaciones' => 'Transf. Check MS',
        ]);

        $r = CierreRendicionMaquinaValormaeSupport::resolver(null, $caja);

        $this->assertSame(8, $r['codigo']);
        $this->assertTrue(CierreRendicionMaquinaValormaeSupport::esCuentaFinanciera($r['codigo'], $r['tipo']));
    }

    public function test_deposito_qr_por_nombre_es_codigo_25(): void
    {
        $caja = new Cuentacaja([
            'codigo' => '25',
            'nombre' => 'Deposito efectivo pago QR',
            'descripcion_operaciones' => 'DEPOSITO EFECTIVO PAGO QR',
        ]);

        $r = CierreRendicionMaquinaValormaeSupport::resolver(null, $caja);

        $this->assertSame(25, $r['codigo']);
        $this->assertTrue(CierreRendicionMaquinaValormaeSupport::esTotalcoinSlot(25));
    }
}

<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\PagoproveedorImputacionApSupport;
use Tests\TestCase;

class PagoproveedorImputacionApSupportTest extends TestCase
{
    public function test_opp_y_opa_son_credito_aop_invierte(): void
    {
        $this->assertSame('OPP', PagoproveedorImputacionApSupport::tipoDesdeComprobante('OPP'));
        $this->assertSame('OPA', PagoproveedorImputacionApSupport::tipoDesdeComprobante('opa'));
        $this->assertSame('AOP', PagoproveedorImputacionApSupport::tipoDesdeComprobante('AOP'));
        $this->assertSame(-1, PagoproveedorImputacionApSupport::signoHaberNeto('OPP'));
        $this->assertSame(-1, PagoproveedorImputacionApSupport::signoHaberNeto('OPA'));
        $this->assertSame(1, PagoproveedorImputacionApSupport::signoHaberNeto('AOP'));
    }

    public function test_cuatro_patas_cuadran(): void
    {
        $eval = PagoproveedorImputacionApSupport::evaluarCuatroPatas(
            -1500.0,
            -1500.0,
            -1500.0,
            -1500.0,
            true,
            true,
            true,
            true,
            PagoproveedorImputacionApSupport::TOLERANCIA,
            -200.0,
            -200.0,
            -1500.0
        );

        $this->assertTrue($eval['ok']);
        $this->assertSame([], $eval['alertas']);
        $this->assertSame(0.0, $eval['diff_cc_asiento']);
        $this->assertSame(0.0, $eval['diff_cc_op']);
    }

    public function test_cuatro_patas_desvio_promov_y_ctamov(): void
    {
        $eval = PagoproveedorImputacionApSupport::evaluarCuatroPatas(
            -1500.0,
            -1500.0,
            -1400.0,
            -1490.0,
            true,
            true,
            true,
            true,
            PagoproveedorImputacionApSupport::TOLERANCIA,
            0.0,
            0.0,
            -1500.0
        );

        $this->assertFalse($eval['ok']);
        $this->assertContains('Promov ≠ OP', $eval['alertas']);
        $this->assertContains('Asiento ≠ ctamov', $eval['alertas']);
        $this->assertContains('CC ≠ ctamov', $eval['alertas']);
        $this->assertSame(100.0, $eval['diff_cc_promov']);
        $this->assertSame(10.0, $eval['diff_asiento_ctamov']);
    }

    public function test_cuatro_patas_faltantes(): void
    {
        $eval = PagoproveedorImputacionApSupport::evaluarCuatroPatas(
            0.0,
            0.0,
            0.0,
            0.0,
            false,
            false,
            false,
            false
        );

        $this->assertFalse($eval['ok']);
        $this->assertContains('Sin CC', $eval['alertas']);
        $this->assertContains('Sin asiento', $eval['alertas']);
        $this->assertContains('Sin promov Anita', $eval['alertas']);
        $this->assertContains('Sin ctamov Anita', $eval['alertas']);
    }

    public function test_cruzada_no_compara_promov_contra_ap(): void
    {
        $eval = PagoproveedorImputacionApSupport::evaluarCuatroPatas(
            -29788595.85,
            -29788595.85,
            -38558862.30,
            -29788595.85,
            true,
            true,
            true,
            true,
            PagoproveedorImputacionApSupport::TOLERANCIA,
            0.0,
            0.0,
            -38558862.30
        );

        $this->assertTrue($eval['ok']);
        $this->assertSame([], $eval['alertas']);
        $this->assertSame(-8770266.45, $eval['diff_cc_promov']);
    }

    public function test_anticipo_desfasado_no_confunde_el_trio(): void
    {
        $eval = PagoproveedorImputacionApSupport::evaluarCuatroPatas(
            -1500.0,
            -1500.0,
            -1500.0,
            -1500.0,
            true,
            true,
            true,
            true,
            PagoproveedorImputacionApSupport::TOLERANCIA,
            -400.0,
            0.0
        );

        $this->assertFalse($eval['ok']);
        $this->assertContains('Anticipo asiento ≠ ctamov', $eval['alertas']);
        $this->assertNotContains('CC ≠ asiento', $eval['alertas']);
    }

    public function test_pre_carga_no_cuenta_como_desvio(): void
    {
        $partes = PagoproveedorImputacionApSupport::particionarControlDiario([
            ['estado' => 'CONFIRMADA', 'ok' => true, 'etiqueta' => 'OK-1'],
            ['estado' => 'PRE CARGA', 'ok' => false, 'etiqueta' => 'OPP 1-99'],
            ['estado' => 'PAGADA', 'ok' => false, 'etiqueta' => 'DESVIO-1'],
        ]);

        $this->assertCount(1, $partes['ok']);
        $this->assertCount(1, $partes['desvios']);
        $this->assertCount(1, $partes['borradores']);
        $this->assertSame('OPP 1-99', $partes['borradores'][0]['etiqueta']);
        $this->assertTrue(PagoproveedorImputacionApSupport::esBorrador('pre carga'));
        $this->assertTrue(PagoproveedorImputacionApSupport::esAnulado('REVERTIDA'));
        $this->assertFalse(PagoproveedorImputacionApSupport::esBorrador('CONFIRMADA'));
    }

    public function test_origen_ie_por_solicitud_o_tipo(): void
    {
        $this->assertTrue(PagoproveedorImputacionApSupport::esOrigenIngresoEgreso(15, 'OPP'));
        $this->assertTrue(PagoproveedorImputacionApSupport::esOrigenIngresoEgreso(null, 'ING'));
        $this->assertTrue(PagoproveedorImputacionApSupport::esOrigenIngresoEgreso(0, 'tra'));
        $this->assertFalse(PagoproveedorImputacionApSupport::esOrigenIngresoEgreso(null, 'OPP'));
        $this->assertFalse(PagoproveedorImputacionApSupport::esOrigenIngresoEgreso(0, 'OPP'));
    }

    public function test_pago_tesoreria_sin_ap_sale_del_control_de_proveedores(): void
    {
        $this->assertTrue(PagoproveedorImputacionApSupport::esPagoSinTrioAp(false, true, 0.0));
        $this->assertFalse(PagoproveedorImputacionApSupport::esPagoSinTrioAp(true, true, 0.0));
        $this->assertFalse(PagoproveedorImputacionApSupport::esPagoSinTrioAp(false, true, -325219869.18));
        $this->assertFalse(PagoproveedorImputacionApSupport::esPagoSinTrioAp(false, false, 0.0));
    }

    public function test_sin_cc_con_trio_ap_sigue_siendo_desvio(): void
    {
        $eval = PagoproveedorImputacionApSupport::evaluarCuatroPatas(
            0.0,
            -1500.0,
            0.0,
            -1500.0,
            false,
            true,
            false,
            true
        );

        $this->assertFalse($eval['ok']);
        $this->assertContains('Sin CC', $eval['alertas']);
        $this->assertContains('Sin promov Anita', $eval['alertas']);
        $this->assertNotContains('Sin asiento', $eval['alertas']);
        $this->assertNotContains('Sin ctamov Anita', $eval['alertas']);
    }
}

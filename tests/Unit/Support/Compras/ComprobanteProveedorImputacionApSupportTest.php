<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorImputacionApSupport;
use Tests\TestCase;

/**
 * Tests sin RefreshDatabase: solo la lógica de comparación (usa config de moneda).
 */
class ComprobanteProveedorImputacionApSupportTest extends TestCase
{
    public function test_factura_haber_ap_mn_sin_distorsion(): void
    {
        $catalogo = $this->catalogo(10, 20, 30);
        $esperado = ComprobanteProveedorImputacionApSupport::esperadoHaberNetoComprobante(
            1000,
            1,
            1,
            '2026-08-01',
            false
        );
        $this->assertSame(1000.0, $esperado);

        $imputado = ComprobanteProveedorImputacionApSupport::imputacionTrio([
            ['cuentacontable_id' => 10, 'monto' => -1000, 'moneda_id' => 1, 'cotizacion' => 1, 'fecha' => '2026-08-01'],
        ], $catalogo, 'test');

        $this->assertSame(1000.0, $imputado['trio']);
        $this->assertSame(1000.0, $imputado['ap']);
        $this->assertSame(1000.0, $imputado['ap_mn']);
        $this->assertSame(ComprobanteProveedorImputacionApSupport::CUBETA_MN, $imputado['cubeta']);

        $eval = ComprobanteProveedorImputacionApSupport::evaluar(
            $esperado,
            $imputado['trio'],
            ComprobanteProveedorImputacionApSupport::CUBETA_MN,
            $imputado['cubeta'],
            true,
            false,
            ComprobanteProveedorImputacionApSupport::TIPO_COMPROBANTE
        );
        $this->assertTrue($eval['ok']);
        $this->assertSame(0.0, $eval['diferencia']);
    }

    public function test_nota_credito_debe_ap(): void
    {
        $esperado = ComprobanteProveedorImputacionApSupport::esperadoHaberNetoComprobante(
            500,
            1,
            1,
            '2026-08-01',
            true
        );
        $this->assertSame(-500.0, $esperado);
        $this->assertTrue(ComprobanteProveedorImputacionApSupport::esNotaCredito('R'));
    }

    public function test_factura_me_usa_cotizacion_de_la_operacion(): void
    {
        $catalogo = $this->catalogo(10, 20, 30);
        $esperado = ComprobanteProveedorImputacionApSupport::esperadoHaberNetoComprobante(
            100,
            2,
            1200,
            '2026-08-01',
            false
        );
        $this->assertSame(120000.0, $esperado);

        $imputado = ComprobanteProveedorImputacionApSupport::imputacionTrio([
            ['cuentacontable_id' => 20, 'monto' => -100, 'moneda_id' => 2, 'cotizacion' => 1200, 'fecha' => '2026-08-01'],
        ], $catalogo, 'test me');

        $this->assertSame(120000.0, $imputado['ap_me']);
        $this->assertSame(120000.0, $imputado['trio']);
    }

    public function test_aplicacion_opa_neto_cero_en_el_trio(): void
    {
        $catalogo = $this->catalogo(10, 20, 30);
        $imputado = ComprobanteProveedorImputacionApSupport::imputacionTrio([
            ['cuentacontable_id' => 10, 'monto' => 400, 'moneda_id' => 1, 'cotizacion' => 1, 'fecha' => '2026-08-01'],
            ['cuentacontable_id' => 30, 'monto' => -400, 'moneda_id' => 1, 'cotizacion' => 1, 'fecha' => '2026-08-01'],
        ], $catalogo, 'aplicacion');

        $this->assertSame(0.0, $imputado['trio']);
        $this->assertSame(ComprobanteProveedorImputacionApSupport::CUBETA_MIXTA, $imputado['cubeta']);

        $eval = ComprobanteProveedorImputacionApSupport::evaluar(
            ComprobanteProveedorImputacionApSupport::esperadoHaberNetoAplicacion(),
            $imputado['trio'],
            null,
            $imputado['cubeta'],
            true,
            false,
            ComprobanteProveedorImputacionApSupport::TIPO_APLICACION
        );
        $this->assertTrue($eval['ok']);
    }

    public function test_opa_debe_anticipo(): void
    {
        $esperado = ComprobanteProveedorImputacionApSupport::esperadoHaberNetoOpa(800, 1, 1, '2026-08-01');
        $this->assertSame(-800.0, $esperado);

        $catalogo = $this->catalogo(10, 20, 30);
        $imputado = ComprobanteProveedorImputacionApSupport::imputacionTrio([
            ['cuentacontable_id' => 30, 'monto' => 800, 'moneda_id' => 1, 'cotizacion' => 1, 'fecha' => '2026-08-01'],
        ], $catalogo, 'opa');

        $this->assertSame(-800.0, $imputado['anticipo']);
        $this->assertSame(-800.0, $imputado['trio']);
    }

    public function test_factura_que_imputa_anticipo_es_distorsion(): void
    {
        $catalogo = $this->catalogo(10, 20, 30);
        $imputado = ComprobanteProveedorImputacionApSupport::imputacionTrio([
            ['cuentacontable_id' => 30, 'monto' => -1000, 'moneda_id' => 1, 'cotizacion' => 1, 'fecha' => '2026-08-01'],
        ], $catalogo, 'mal');

        $eval = ComprobanteProveedorImputacionApSupport::evaluar(
            1000.0,
            $imputado['trio'],
            ComprobanteProveedorImputacionApSupport::CUBETA_MN,
            $imputado['cubeta'],
            true,
            false,
            ComprobanteProveedorImputacionApSupport::TIPO_COMPROBANTE
        );
        $this->assertFalse($eval['ok']);
        $this->assertContains('El comprobante imputó anticipo', $eval['alertas']);
        $this->assertContains('Cuenta distinta a la esperada (MN/ME/anticipo)', $eval['alertas']);
    }

    public function test_sin_asiento_alerta(): void
    {
        $eval = ComprobanteProveedorImputacionApSupport::evaluar(
            100.0,
            0.0,
            ComprobanteProveedorImputacionApSupport::CUBETA_MN,
            ComprobanteProveedorImputacionApSupport::CUBETA_NINGUNA,
            false,
            false,
            ComprobanteProveedorImputacionApSupport::TIPO_COMPROBANTE
        );
        $this->assertFalse($eval['ok']);
        $this->assertContains('Sin asiento', $eval['alertas']);
        $this->assertContains('Distorsión en AP/anticipo', $eval['alertas']);
    }

    public function test_tres_patas_cuadran(): void
    {
        $eval = ComprobanteProveedorImputacionApSupport::evaluarTresPatas(
            1500.0,
            1500.0,
            1500.0,
            true,
            true,
            true
        );
        $this->assertTrue($eval['ok']);
        $this->assertSame([], $eval['alertas']);
        $this->assertSame(0.0, $eval['diff_cc_asiento']);
        $this->assertSame(0.0, $eval['diff_asiento_ctamov']);
    }

    public function test_tres_patas_desvio_asiento_ctamov(): void
    {
        $eval = ComprobanteProveedorImputacionApSupport::evaluarTresPatas(
            1500.0,
            1500.0,
            1490.0,
            true,
            true,
            true
        );
        $this->assertFalse($eval['ok']);
        $this->assertContains('Asiento ≠ ctamov', $eval['alertas']);
        $this->assertContains('CC ≠ ctamov', $eval['alertas']);
        $this->assertSame(-10.0, $eval['diff_asiento_ctamov']);
    }

    public function test_tres_patas_sin_ctamov(): void
    {
        $eval = ComprobanteProveedorImputacionApSupport::evaluarTresPatas(
            100.0,
            100.0,
            0.0,
            true,
            true,
            false
        );
        $this->assertFalse($eval['ok']);
        $this->assertContains('Sin ctamov Anita', $eval['alertas']);
    }

    public function test_factura_anticipada_haber_ap_cuadra_con_cc(): void
    {
        $catalogo = $this->catalogo(10, 20, 30);
        $imputado = ComprobanteProveedorImputacionApSupport::imputacionTrio([
            ['cuentacontable_id' => 30, 'monto' => 1175000, 'moneda_id' => 1, 'cotizacion' => 1, 'fecha' => '2026-08-19'],
            ['cuentacontable_id' => 10, 'monto' => -1175000, 'moneda_id' => 1, 'cotizacion' => 1, 'fecha' => '2026-08-19'],
        ], $catalogo, 'anticipada');

        $this->assertSame(0.0, $imputado['trio']);
        $this->assertSame(1175000.0, $imputado['ap']);
        $this->assertSame(-1175000.0, $imputado['anticipo']);
        $this->assertSame(ComprobanteProveedorImputacionApSupport::CUBETA_MIXTA, $imputado['cubeta']);

        $eval = ComprobanteProveedorImputacionApSupport::evaluar(
            1175000.0,
            ComprobanteProveedorImputacionApSupport::haberAp($imputado),
            ComprobanteProveedorImputacionApSupport::CUBETA_MN,
            $imputado['cubeta'],
            true,
            false,
            ComprobanteProveedorImputacionApSupport::TIPO_COMPROBANTE
        );
        $this->assertTrue($eval['ok']);
        $this->assertSame([], $eval['alertas']);

        $tres = ComprobanteProveedorImputacionApSupport::evaluarTresPatas(
            1175000.0,
            $imputado['ap'],
            1175000.0,
            true,
            true,
            true,
            ComprobanteProveedorImputacionApSupport::TOLERANCIA,
            $imputado['anticipo'],
            -1175000.0
        );
        $this->assertTrue($tres['ok']);
        $this->assertSame([], $tres['alertas']);
    }

    public function test_tres_patas_anticipo_desfasado_en_ctamov(): void
    {
        $eval = ComprobanteProveedorImputacionApSupport::evaluarTresPatas(
            1175000.0,
            1175000.0,
            1175000.0,
            true,
            true,
            true,
            ComprobanteProveedorImputacionApSupport::TOLERANCIA,
            -1175000.0,
            0.0
        );
        $this->assertFalse($eval['ok']);
        $this->assertContains('Anticipo asiento ≠ ctamov', $eval['alertas']);
        $this->assertNotContains('CC ≠ asiento', $eval['alertas']);
    }

    public function test_clasificar_codigo_ap(): void
    {
        $catalogo = [
            'codigo_mn' => [211010001 => true],
            'codigo_me' => [211010011 => true],
            'codigo_anticipo' => [113010020 => true],
        ];
        $this->assertSame(
            ComprobanteProveedorImputacionApSupport::CUBETA_MN,
            ComprobanteProveedorImputacionApSupport::clasificarCodigo(211010001, $catalogo)
        );
        $this->assertSame(
            ComprobanteProveedorImputacionApSupport::CUBETA_ME,
            ComprobanteProveedorImputacionApSupport::clasificarCodigo(211010011, $catalogo)
        );
        $this->assertNull(ComprobanteProveedorImputacionApSupport::clasificarCodigo(111010001, $catalogo));
    }

    /**
     * @return array{mn: array<int, true>, me: array<int, true>, anticipo: array<int, true>}
     */
    private function catalogo(int $mn, int $me, int $anticipo): array
    {
        return [
            'mn' => [$mn => true],
            'me' => [$me => true],
            'anticipo' => [$anticipo => true],
        ];
    }
}

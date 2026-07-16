<?php

namespace Tests\Unit\Support\Compras\Retencion;

use App\Support\Compras\Retencion\RetencionIvaCalculoSupport;
use App\Support\Compras\Retencion\RetencionIvaInput;
use App\Support\Compras\Retencion\RetencionIvaRegimen;
use App\Support\Compras\Retencion\RetencionIvaResultado;
use App\Support\Compras\Retencion\RetencionRegimenResolver;
use PHPUnit\Framework\TestCase;

/**
 * Motor puro IVA + resolver de régimen — sin BD.
 */
class RetencionIvaCalculoSupportTest extends TestCase
{
    private RetencionIvaCalculoSupport $support;

    protected function setUp(): void
    {
        parent::setUp();
        $this->support = new RetencionIvaCalculoSupport;
    }

    public function test_no_retiene_si_flag_apagado(): void
    {
        $r = $this->support->calcular(new RetencionIvaInput(
            $this->regimenSobreIva50(),
            10000.0,
            2100.0,
            false,
        ));

        $this->assertFalse($r->aplica);
        $this->assertSame(RetencionIvaResultado::MOTIVO_NO_RETIENE, $r->motivo);
    }

    public function test_excluido_no_retiene(): void
    {
        $r = $this->support->calcular(new RetencionIvaInput(
            $this->regimenSobreIva50(),
            10000.0,
            2100.0,
            true,
            0.0,
            0.0,
            0.0,
            null,
            true,
        ));

        $this->assertFalse($r->aplica);
        $this->assertSame(RetencionIvaResultado::MOTIVO_EXCLUIDO, $r->motivo);
    }

    public function test_sobre_iva_cincuenta_por_ciento(): void
    {
        // IVA 2.100 × 50% = 1.050
        $r = $this->support->calcular(new RetencionIvaInput(
            $this->regimenSobreIva50(),
            10000.0,
            2100.0,
            true,
        ));

        $this->assertTrue($r->aplica);
        $this->assertEqualsWithDelta(1050.0, $r->importeRetencion, 0.01);
        $this->assertEqualsWithDelta(2100.0, $r->baseCalculo, 0.01);
        $this->assertEqualsWithDelta(50.0, $r->alicuotaAplicada, 0.01);
        $this->assertSame('sobre_iva', $r->detalle['modo']);
    }

    public function test_sobre_iva_bajo_minimo(): void
    {
        $r = $this->support->calcular(new RetencionIvaInput(
            $this->regimenSobreIva50(),
            400.0,
            84.0,
            true,
        ));

        $this->assertFalse($r->aplica);
        $this->assertSame(RetencionIvaResultado::MOTIVO_BAJO_MINIMO_IMPONIBLE, $r->motivo);
    }

    public function test_reproweb_override_cien_por_ciento(): void
    {
        // IVA 2.100 × 100% = 2.100
        $r = $this->support->calcular(new RetencionIvaInput(
            $this->regimenSobreIva50(),
            10000.0,
            2100.0,
            true,
            0.0,
            0.0,
            0.0,
            100.0,
        ));

        $this->assertTrue($r->aplica);
        $this->assertEqualsWithDelta(2100.0, $r->importeRetencion, 0.01);
        $this->assertTrue($r->detalle['override']);
    }

    public function test_factura_leyenda_cien_sobre_iva(): void
    {
        $regimen = new RetencionIvaRegimen(
            1, '1', 'Facturas A CON LEYENDA', '499', 'I', 100.0, 210.0, 0.0, 0,
        );

        $r = $this->support->calcular(new RetencionIvaInput($regimen, 10000.0, 2100.0, true));

        $this->assertTrue($r->aplica);
        $this->assertEqualsWithDelta(2100.0, $r->importeRetencion, 0.01);
    }

    public function test_sobre_neto_sin_acumular(): void
    {
        // Limpieza 21% sobre neto 10.000 = 2.100 (baseimponible umbral 8.000)
        $regimen = new RetencionIvaRegimen(
            7, '21', 'Limpieza 21%', '831', 'N', 21.0, 0.0, 8000.0, 1,
        );

        $r = $this->support->calcular(new RetencionIvaInput($regimen, 10000.0, 2100.0, true));

        $this->assertTrue($r->aplica);
        $this->assertEqualsWithDelta(2100.0, $r->importeRetencion, 0.01);
        $this->assertSame('sobre_neto', $r->detalle['modo']);
    }

    public function test_sobre_neto_bajo_base_periodo(): void
    {
        $regimen = new RetencionIvaRegimen(
            7, '21', 'Limpieza 21%', '831', 'N', 21.0, 0.0, 8000.0, 1,
        );

        $r = $this->support->calcular(new RetencionIvaInput($regimen, 5000.0, 1050.0, true));

        $this->assertFalse($r->aplica);
        $this->assertSame(RetencionIvaResultado::MOTIVO_BAJO_BASE_PERIODO, $r->motivo);
    }

    public function test_acumulado_periodos_resta_previo(): void
    {
        // Monotributo O: umbral 400.000 — período 450.000 × 10,5% = 47.250 − 42.000 = 5.250
        $regimen = new RetencionIvaRegimen(
            5, '12', 'Monot.10.5% Servicios', '777', 'O', 10.5, 0.0, 400000.0, 12,
        );

        $r = $this->support->calcular(new RetencionIvaInput(
            $regimen,
            50000.0,
            0.0,
            true,
            400000.0,
            0.0,
            42000.0,
        ));

        $this->assertTrue($r->aplica);
        $this->assertEqualsWithDelta(5250.0, $r->importeRetencion, 0.01);
        $this->assertSame('sobre_neto_acumulado', $r->detalle['modo']);
    }

    public function test_codigo_cero_no_aplica(): void
    {
        $regimen = new RetencionIvaRegimen(
            9, '0', 'Sin código de retención de IVA', '0', 'N', 0.0, 0.0, 0.0, 0,
        );

        $r = $this->support->calcular(new RetencionIvaInput($regimen, 10000.0, 2100.0, true));

        $this->assertFalse($r->aplica);
        $this->assertSame(RetencionIvaResultado::MOTIVO_NO_RETIENE, $r->motivo);
    }

    public function test_resolver_precedencia_pago_sobre_comprobante_y_proveedor(): void
    {
        $this->assertSame(30, RetencionRegimenResolver::resolverId(30, 20, 10));
        $this->assertSame(20, RetencionRegimenResolver::resolverId(null, 20, 10));
        $this->assertSame(10, RetencionRegimenResolver::resolverId(null, null, 10));
        $this->assertNull(RetencionRegimenResolver::resolverId(null, null, null));
        $this->assertSame(20, RetencionRegimenResolver::resolverId(0, 20, 10));
    }

    private function regimenSobreIva50(): RetencionIvaRegimen
    {
        return new RetencionIvaRegimen(
            2, '2', 'Retencion IVA 10.5%', '499', 'I', 50.0, 104.99, 0.0, 0,
        );
    }
}

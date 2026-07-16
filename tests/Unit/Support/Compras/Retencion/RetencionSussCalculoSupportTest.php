<?php

namespace Tests\Unit\Support\Compras\Retencion;

use App\Support\Compras\Retencion\RetencionSussCalculoSupport;
use App\Support\Compras\Retencion\RetencionSussInput;
use App\Support\Compras\Retencion\RetencionSussRegimen;
use App\Support\Compras\Retencion\RetencionSussResultado;
use PHPUnit\Framework\TestCase;

/**
 * Motor puro SUSS — sin BD.
 */
class RetencionSussCalculoSupportTest extends TestCase
{
    private RetencionSussCalculoSupport $support;

    protected function setUp(): void
    {
        parent::setUp();
        $this->support = new RetencionSussCalculoSupport;
    }

    public function test_no_retiene_si_flag_apagado(): void
    {
        $r = $this->support->calcular(new RetencionSussInput(
            $this->regimenGeneral1784(),
            100000.0,
            false,
        ));

        $this->assertFalse($r->aplica);
        $this->assertSame(RetencionSussResultado::MOTIVO_NO_RETIENE, $r->motivo);
    }

    public function test_no_sujeto_pasible(): void
    {
        $r = $this->support->calcular(new RetencionSussInput(
            $this->regimenGeneral1784(),
            100000.0,
            true,
            false,
        ));

        $this->assertFalse($r->aplica);
        $this->assertSame(RetencionSussResultado::MOTIVO_NO_SUJETO, $r->motivo);
    }

    public function test_rg1784_uno_por_ciento_sobre_neto(): void
    {
        // 100.000 × 1% = 1.000 (≥ mínimo $400)
        $r = $this->support->calcular(new RetencionSussInput(
            $this->regimenGeneral1784(),
            100000.0,
            true,
        ));

        $this->assertTrue($r->aplica);
        $this->assertEqualsWithDelta(1000.0, $r->importeRetencion, 0.01);
        $this->assertEqualsWithDelta(1.0, $r->alicuotaAplicada, 0.01);
        $this->assertSame(RetencionSussResultado::MOTIVO_OK, $r->motivo);
    }

    public function test_rg1784_bajo_minimo_retencion(): void
    {
        // 30.000 × 1% = 300 < 400
        $r = $this->support->calcular(new RetencionSussInput(
            $this->regimenGeneral1784(),
            30000.0,
            true,
        ));

        $this->assertFalse($r->aplica);
        $this->assertSame(RetencionSussResultado::MOTIVO_BAJO_MINIMO_RETENCION, $r->motivo);
    }

    public function test_limpieza_seis_por_ciento(): void
    {
        // RG 1556: 50.000 × 6% = 3.000
        $r = $this->support->calcular(new RetencionSussInput(
            $this->regimenLimpieza1556(),
            50000.0,
            true,
        ));

        $this->assertTrue($r->aplica);
        $this->assertEqualsWithDelta(3000.0, $r->importeRetencion, 0.01);
        $this->assertEqualsWithDelta(6.0, $r->alicuotaAplicada, 0.01);
    }

    public function test_acumulado_mensual_bajo_umbral(): void
    {
        // Ingeniería: umbral 1.500.000 — período 1.000.000 → no retiene
        $r = $this->support->calcular(new RetencionSussInput(
            $this->regimenConstruccionIngenieria(),
            400000.0,
            true,
            true,
            600000.0,
            0.0,
        ));

        $this->assertFalse($r->aplica);
        $this->assertSame(RetencionSussResultado::MOTIVO_BAJO_MINIMO_IMPONIBLE, $r->motivo);
    }

    public function test_acumulado_mensual_sobre_umbral_resta_previo(): void
    {
        // Período 1.600.000 × 1,2% = 19.200 − previo 12.000 = 7.200
        $r = $this->support->calcular(new RetencionSussInput(
            $this->regimenConstruccionIngenieria(),
            400000.0,
            true,
            true,
            1200000.0,
            12000.0,
        ));

        $this->assertTrue($r->aplica);
        $this->assertEqualsWithDelta(1600000.0, $r->baseCalculo, 0.01);
        $this->assertEqualsWithDelta(7200.0, $r->importeRetencion, 0.01);
        $this->assertSame('porcentaje_acumulado', $r->detalle['modo']);
    }

    public function test_importe_fijo_desde_catalogo(): void
    {
        $regimen = new RetencionSussRegimen(
            6, '6', 'Eventuales', '742', 'I', 0.0, 500.0, 0.0,
        );

        $r = $this->support->calcular(new RetencionSussInput($regimen, 10000.0, true));

        $this->assertTrue($r->aplica);
        $this->assertEqualsWithDelta(500.0, $r->importeRetencion, 0.01);
        $this->assertSame('importe_fijo', $r->detalle['modo']);
    }

    public function test_importe_cero_requiere_manual(): void
    {
        $regimen = new RetencionSussRegimen(
            6, '6', 'Eventuales', '742', 'I', 0.0, 0.0, 0.0,
        );

        $r = $this->support->calcular(new RetencionSussInput($regimen, 10000.0, true));

        $this->assertFalse($r->aplica);
        $this->assertSame(RetencionSussResultado::MOTIVO_MANUAL_REQUERIDO, $r->motivo);
    }

    public function test_importe_manual(): void
    {
        $regimen = new RetencionSussRegimen(
            6, '6', 'Eventuales', '742', 'I', 0.0, 0.0, 0.0,
        );

        $r = $this->support->calcular(new RetencionSussInput(
            $regimen, 10000.0, true, true, 0.0, 0.0, 850.0,
        ));

        $this->assertTrue($r->aplica);
        $this->assertEqualsWithDelta(850.0, $r->importeRetencion, 0.01);
        $this->assertSame(RetencionSussResultado::MOTIVO_OK_MANUAL, $r->motivo);
    }

    public function test_codigo_cero_no_aplica(): void
    {
        $regimen = new RetencionSussRegimen(
            9, '0', 'Sin código de retención de SUSS', '0', 'N', 0.0, 0.0, 0.0,
        );

        $r = $this->support->calcular(new RetencionSussInput($regimen, 100000.0, true));

        $this->assertFalse($r->aplica);
        $this->assertSame(RetencionSussResultado::MOTIVO_NO_RETIENE, $r->motivo);
    }

    private function regimenGeneral1784(): RetencionSussRegimen
    {
        return new RetencionSussRegimen(
            10, '10', 'R.G. 1784 Régimen general', '755', 'P',
            0.0, 1.0, 400.0,
        );
    }

    private function regimenLimpieza1556(): RetencionSussRegimen
    {
        return new RetencionSussRegimen(
            1, '1', 'R.G. 1556', '748', 'P', 0.0, 6.0, 0.0,
        );
    }

    private function regimenConstruccionIngenieria(): RetencionSussRegimen
    {
        return new RetencionSussRegimen(
            7, '7', 'R.G. 2682 Ingenieria', '740', 'A',
            1500000.0, 1.2, 0.0,
        );
    }
}

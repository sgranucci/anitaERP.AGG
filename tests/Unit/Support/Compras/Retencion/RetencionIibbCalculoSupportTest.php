<?php

namespace Tests\Unit\Support\Compras\Retencion;

use App\Support\Compras\Retencion\RetencionIibbCalculoSupport;
use App\Support\Compras\Retencion\RetencionIibbInput;
use App\Support\Compras\Retencion\RetencionIibbResultado;
use PHPUnit\Framework\TestCase;

/**
 * Motor puro IIBB — sin BD.
 */
class RetencionIibbCalculoSupportTest extends TestCase
{
    private RetencionIibbCalculoSupport $support;

    protected function setUp(): void
    {
        parent::setUp();
        $this->support = new RetencionIibbCalculoSupport;
    }

    public function test_no_retiene(): void
    {
        $r = $this->support->calcular(new RetencionIibbInput(100000.0, 3.0, false));

        $this->assertFalse($r->aplica);
        $this->assertSame(RetencionIibbResultado::MOTIVO_NO_RETIENE, $r->motivo);
    }

    public function test_padron_tres_por_ciento(): void
    {
        // 100.000 × 3% = 3.000
        $r = $this->support->calcular(new RetencionIibbInput(
            100000.0,
            3.0,
            true,
            19000.0,
            0.0,
            'padron',
            '902',
            2,
            1,
        ));

        $this->assertTrue($r->aplica);
        $this->assertEqualsWithDelta(3000.0, $r->importeRetencion, 0.01);
        $this->assertEqualsWithDelta(3.0, $r->alicuotaAplicada, 0.01);
        $this->assertSame('padron', $r->detalle['origen_tasa']);
    }

    public function test_bajo_minimo_imponible(): void
    {
        $r = $this->support->calcular(new RetencionIibbInput(
            10000.0,
            4.0,
            true,
            19000.0,
        ));

        $this->assertFalse($r->aplica);
        $this->assertSame(RetencionIibbResultado::MOTIVO_BAJO_MINIMO_IMPONIBLE, $r->motivo);
    }

    public function test_fallback_cuatro_por_ciento(): void
    {
        $r = $this->support->calcular(new RetencionIibbInput(
            50000.0,
            4.0,
            true,
            19000.0,
            0.0,
            'fallback',
        ));

        $this->assertTrue($r->aplica);
        $this->assertEqualsWithDelta(2000.0, $r->importeRetencion, 0.01);
        $this->assertSame('fallback', $r->detalle['origen_tasa']);
    }

    public function test_bajo_minimo_retencion(): void
    {
        // 20.000 × 0,5% = 100 < mínimo 150
        $r = $this->support->calcular(new RetencionIibbInput(
            20000.0,
            0.5,
            true,
            19000.0,
            150.0,
            'padron',
        ));

        $this->assertFalse($r->aplica);
        $this->assertSame(RetencionIibbResultado::MOTIVO_BAJO_MINIMO_RETENCION, $r->motivo);
    }

    public function test_tasa_cero_sin_tasa(): void
    {
        $r = $this->support->calcular(new RetencionIibbInput(100000.0, 0.0, true));

        $this->assertFalse($r->aplica);
        $this->assertSame(RetencionIibbResultado::MOTIVO_SIN_TASA, $r->motivo);
    }
}

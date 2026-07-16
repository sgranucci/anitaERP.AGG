<?php

namespace Tests\Unit\Support\Compras\Retencion;

use App\Support\Compras\Retencion\RetencionGananciasCalculoSupport;
use App\Support\Compras\Retencion\RetencionGananciasEscalaFila;
use App\Support\Compras\Retencion\RetencionGananciasInput;
use App\Support\Compras\Retencion\RetencionGananciasRegimen;
use App\Support\Compras\Retencion\RetencionGananciasResultado;
use PHPUnit\Framework\TestCase;

/**
 * Motor puro RG 830 — sin BD.
 */
class RetencionGananciasCalculoSupportTest extends TestCase
{
    private RetencionGananciasCalculoSupport $support;

    protected function setUp(): void
    {
        parent::setUp();
        $this->support = new RetencionGananciasCalculoSupport;
    }

    public function test_no_retiene_si_flag_apagado(): void
    {
        $r = $this->support->calcular(new RetencionGananciasInput(
            $this->regimenLocacionObras(),
            100000.0,
            false,
            true,
        ));

        $this->assertFalse($r->aplica);
        $this->assertSame(RetencionGananciasResultado::MOTIVO_NO_RETIENE, $r->motivo);
        $this->assertSame(0.0, $r->importeRetencion);
    }

    public function test_locacion_inscripto_sobre_excedente_porcentaje_fijo(): void
    {
        // Neto 100.000 − MNSR 67.170 = 32.830 × 2% = 656.60
        $r = $this->support->calcular(new RetencionGananciasInput(
            $this->regimenLocacionObras(),
            100000.0,
            true,
            true,
        ));

        $this->assertTrue($r->aplica);
        $this->assertSame(RetencionGananciasResultado::MOTIVO_OK, $r->motivo);
        $this->assertEqualsWithDelta(32830.0, $r->baseRetenible, 0.01);
        $this->assertEqualsWithDelta(656.60, $r->importeRetencion, 0.01);
        $this->assertEqualsWithDelta(2.0, $r->alicuotaAplicada, 0.01);
    }

    public function test_bajo_minimo_no_sujeto(): void
    {
        $r = $this->support->calcular(new RetencionGananciasInput(
            $this->regimenLocacionObras(),
            50000.0,
            true,
            true,
        ));

        $this->assertFalse($r->aplica);
        $this->assertSame(RetencionGananciasResultado::MOTIVO_BAJO_MINIMO_NO_SUJETO, $r->motivo);
    }

    public function test_no_inscripto_sin_restar_mnsr(): void
    {
        // 100.000 × 28% = 28.000
        $r = $this->support->calcular(new RetencionGananciasInput(
            $this->regimenLocacionObras(),
            100000.0,
            true,
            false,
        ));

        $this->assertTrue($r->aplica);
        $this->assertEqualsWithDelta(100000.0, $r->baseRetenible, 0.01);
        $this->assertEqualsWithDelta(28000.0, $r->importeRetencion, 0.01);
        $this->assertEqualsWithDelta(28.0, $r->alicuotaAplicada, 0.01);
    }

    public function test_acumulado_resta_retencion_previa(): void
    {
        // Período: previo 80.000 + actual 40.000 = 120.000 − 67.170 = 52.830 × 2% = 1.056.60
        // Previo ya retuvo sobre 80.000: (80.000 − 67.170) × 2% = 256.60
        // Este pago: 1.056.60 − 256.60 = 800.00
        $r = $this->support->calcular(new RetencionGananciasInput(
            $this->regimenLocacionObras(),
            40000.0,
            true,
            true,
            80000.0,
            256.60,
        ));

        $this->assertTrue($r->aplica);
        $this->assertEqualsWithDelta(800.0, $r->importeRetencion, 0.01);
        $this->assertEqualsWithDelta(120000.0, $r->baseCalculo, 0.01);
    }

    public function test_forma_e_no_resta_excedente(): void
    {
        $regimen = new RetencionGananciasRegimen(
            8,
            '9',
            'Facturas M',
            '94',
            'E',
            6.0,
            6.0,
            1000.0,
            0.0,
            0.0,
            0,
            0.0,
        );

        // 5.000 × 6% = 300 (sin restar MNSR)
        $r = $this->support->calcular(new RetencionGananciasInput($regimen, 5000.0, true, true));

        $this->assertTrue($r->aplica);
        $this->assertEqualsWithDelta(5000.0, $r->baseRetenible, 0.01);
        $this->assertEqualsWithDelta(300.0, $r->importeRetencion, 0.01);
    }

    public function test_minimo_retencion_descarta_importe_bajo(): void
    {
        // Base retenible 1.000 × 2% = 20 < mínimo 240
        $r = $this->support->calcular(new RetencionGananciasInput(
            $this->regimenLocacionObras(),
            68170.0,
            true,
            true,
        ));

        $this->assertFalse($r->aplica);
        $this->assertSame(RetencionGananciasResultado::MOTIVO_BAJO_MINIMO_RETENCION, $r->motivo);
    }

    public function test_escala_profesionales_primer_tramo(): void
    {
        // Neto 200.000 − MNSR 160.000 = 40.000 → tramo 0–71.000 al 5% = 2.000
        $r = $this->support->calcular(new RetencionGananciasInput(
            $this->regimenProfesionales(),
            200000.0,
            true,
            true,
        ));

        $this->assertTrue($r->aplica);
        $this->assertEqualsWithDelta(40000.0, $r->baseRetenible, 0.01);
        $this->assertEqualsWithDelta(2000.0, $r->importeRetencion, 0.01);
        $this->assertSame('escala', $r->detalle['modo']);
    }

    public function test_escala_profesionales_segundo_tramo(): void
    {
        // Base retenible 90.000 → 3.550 + (90.000 − 71.000) × 9% = 3.550 + 1.710 = 5.260
        $r = $this->support->calcular(new RetencionGananciasInput(
            $this->regimenProfesionales(),
            250000.0,
            true,
            true,
        ));

        $this->assertTrue($r->aplica);
        $this->assertEqualsWithDelta(90000.0, $r->baseRetenible, 0.01);
        $this->assertEqualsWithDelta(5260.0, $r->importeRetencion, 0.01);
    }

    public function test_manual_requiere_importe(): void
    {
        $regimen = new RetencionGananciasRegimen(
            19, '99', 'Profesiones liberales', '119', 'M',
            0.0, 28.0, 160000.0, 240.0, 0.0, 0, 0.0,
        );

        $r = $this->support->calcular(new RetencionGananciasInput($regimen, 100000.0, true, true));

        $this->assertFalse($r->aplica);
        $this->assertSame(RetencionGananciasResultado::MOTIVO_MANUAL_REQUERIDO, $r->motivo);
    }

    public function test_manual_con_importe(): void
    {
        $regimen = new RetencionGananciasRegimen(
            19, '99', 'Profesiones liberales', '119', 'M',
            0.0, 28.0, 160000.0, 240.0, 0.0, 0, 0.0,
        );

        $r = $this->support->calcular(new RetencionGananciasInput(
            $regimen, 100000.0, true, true, 0.0, 0.0, 1500.0,
        ));

        $this->assertTrue($r->aplica);
        $this->assertEqualsWithDelta(1500.0, $r->importeRetencion, 0.01);
        $this->assertSame(RetencionGananciasResultado::MOTIVO_OK_MANUAL, $r->motivo);
    }

    public function test_grossing_up(): void
    {
        $regimen = new RetencionGananciasRegimen(
            21, '101', 'Premios', '434', 'G',
            39.0, 0.0, 0.0, 0.0, 0.0, 0, 0.0,
        );

        // 1000 × 39 / (100 − 39) = 639.34
        $r = $this->support->calcular(new RetencionGananciasInput($regimen, 1000.0, true, true));

        $this->assertTrue($r->aplica);
        $this->assertEqualsWithDelta(639.34, $r->importeRetencion, 0.01);
    }

    public function test_codigo_cero_no_aplica(): void
    {
        $regimen = new RetencionGananciasRegimen(
            25, '0', 'Sin código de retención de ganancias', '0', 'N',
            0.0, 0.0, 0.0, 0.0, 0.0, 0, 0.0,
        );

        $r = $this->support->calcular(new RetencionGananciasInput($regimen, 100000.0, true, true));

        $this->assertFalse($r->aplica);
        $this->assertSame(RetencionGananciasResultado::MOTIVO_NO_RETIENE, $r->motivo);
    }

    private function regimenLocacionObras(): RetencionGananciasRegimen
    {
        return new RetencionGananciasRegimen(
            2,
            '2',
            'Locacion de Obras/Servicios',
            '94',
            'S',
            2.0,
            28.0,
            67170.0,
            240.0,
            0.0,
            0,
            0.0,
        );
    }

    private function regimenProfesionales(): RetencionGananciasRegimen
    {
        return new RetencionGananciasRegimen(
            4,
            '4',
            'Profesionales liberales',
            '119',
            'S',
            0.0,
            28.0,
            160000.0,
            240.0,
            0.0,
            0,
            0.0,
            [
                new RetencionGananciasEscalaFila(0, 71000, 0, 5, 0),
                new RetencionGananciasEscalaFila(71000, 142000, 3550, 9, 71000),
                new RetencionGananciasEscalaFila(142000, 213000, 9940, 12, 142000),
                new RetencionGananciasEscalaFila(213000, 284000, 18460, 15, 213000),
                new RetencionGananciasEscalaFila(284000, 426000, 29110, 19, 284000),
                new RetencionGananciasEscalaFila(426000, 568000, 56090, 23, 426000),
                new RetencionGananciasEscalaFila(568000, 852000, 56090, 27, 568000),
                new RetencionGananciasEscalaFila(852000, 9999999, 165430, 31, 852000),
            ],
        );
    }
}

<?php

namespace Tests\Unit\Support\Compras\Retencion;

use App\Support\Compras\Retencion\RetencionGananciasCalculoSupport;
use App\Support\Compras\Retencion\RetencionGananciasInput;
use App\Support\Compras\Retencion\RetencionGananciasRegimen;
use App\Support\Compras\Retencion\RetencionGananciasResultado;
use PHPUnit\Framework\TestCase;

/**
 * RG 830: con forma S el motor resta retenciones previas del período (mes).
 */
class RetencionGananciasAcumuladoMesMotorTest extends TestCase
{
    public function test_forma_s_acumula_mes_y_resta_retenido_previo(): void
    {
        $support = new RetencionGananciasCalculoSupport;
        $regimen = new RetencionGananciasRegimen(
            1, '1', 'Bienes', '78', 'S',
            2.0, 10.0, 224000.0, 240.0, 0.0, 0, 0.0,
        );

        // Pago 1 del mes: neto 25.023.425,60 − 224.000 × 2%
        $r1 = $support->calcular(new RetencionGananciasInput(
            $regimen,
            25023425.60,
            true,
            true,
        ));
        $this->assertTrue($r1->aplica);
        $this->assertEqualsWithDelta(495988.51, $r1->importeRetencion, 0.02);

        // Pago 2: mismo mes, acumula neto previo y resta retención previa
        $r2 = $support->calcular(new RetencionGananciasInput(
            $regimen,
            1000000.0,
            true,
            true,
            25023425.60,
            $r1->importeRetencion,
        ));

        // Período: 26.023.425,60 − 224.000 = 25.799.425,60 × 2% = 515.988,51
        // Menos previo 495.988,51 = 20.000,00 (= 1.000.000 × 2%)
        $this->assertTrue($r2->aplica);
        $this->assertEqualsWithDelta(20000.0, $r2->importeRetencion, 0.02);
        $this->assertSame(RetencionGananciasResultado::MOTIVO_OK, $r2->motivo);
        $this->assertEqualsWithDelta(25023425.60, $r2->detalle['neto_acumulado_previo'], 0.01);
        $this->assertEqualsWithDelta($r1->importeRetencion, $r2->detalle['retenido_previo'], 0.02);
    }

    public function test_forma_n_ignora_acumulados(): void
    {
        $support = new RetencionGananciasCalculoSupport;
        $regimen = new RetencionGananciasRegimen(
            1, '1', 'Bienes', '78', 'N',
            2.0, 10.0, 224000.0, 240.0, 0.0, 0, 0.0,
        );

        $r = $support->calcular(new RetencionGananciasInput(
            $regimen,
            1000000.0,
            true,
            true,
            25023425.60,
            495988.51,
        ));

        // Solo el pago: (1.000.000 − 224.000) × 2% = 15.520
        $this->assertEqualsWithDelta(15520.0, $r->importeRetencion, 0.01);
        $this->assertSame(0.0, $r->detalle['retenido_previo']);
    }
}

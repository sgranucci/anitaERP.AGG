<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\IngresoEgresoImputacionDiariaSupport;
use Tests\TestCase;

class IngresoEgresoImputacionDiariaSupportTest extends TestCase
{
    public function test_ing_cuadra_caja_asiento_tesmov_ctamov(): void
    {
        $eval = IngresoEgresoImputacionDiariaSupport::evaluar(
            1000.0,
            0.0,
            1000.0,
            1000.0,
            1000.0,
            1000.0,
            1000.0,
            1000.0,
            1000.0,
            true,
            true,
            true,
            true,
            true,
            'ING',
            0,
            0,
            0
        );

        $this->assertTrue($eval['ok']);
        $this->assertSame([], $eval['alertas']);
    }

    public function test_opp_ie_no_exige_caja_igual_asiento(): void
    {
        $eval = IngresoEgresoImputacionDiariaSupport::evaluar(
            800.0,
            0.0,
            1500.0,
            800.0,
            800.0,
            1500.0,
            1500.0,
            1500.0,
            1500.0,
            true,
            true,
            true,
            true,
            true,
            'OPP',
            0,
            0,
            0
        );

        $this->assertTrue($eval['ok']);
        $this->assertNotContains('Caja ≠ asiento', $eval['alertas']);
    }

    public function test_tra_tesmov_segun_sucursal_auxpag(): void
    {
        $this->assertSame(['TED'], IngresoEgresoImputacionDiariaSupport::tiposTesmovTraDesdeSucursalAxp(0));
        $this->assertSame(['TEH'], IngresoEgresoImputacionDiariaSupport::tiposTesmovTraDesdeSucursalAxp(1));
        $this->assertSame(
            ['TED', 'TEH'],
            IngresoEgresoImputacionDiariaSupport::tiposTesmovTraDesdeSucursalAxp(-1)
        );
    }

    public function test_tra_pierna_alfanumerica_cuando_auxpag_quedo_en_ceros(): void
    {
        $macro = (object) ['tesv_cuenta' => '00000127', 'tesv_importe' => 4800000];
        $mp = (object) ['tesv_cuenta' => '0000GMEP', 'tesv_importe' => 4800000];

        $piernaMacro = IngresoEgresoImputacionDiariaSupport::elegirFilasTesmovPierna(
            [$macro, $mp],
            '00000127'
        );
        $piernaMp = IngresoEgresoImputacionDiariaSupport::elegirFilasTesmovPierna(
            [$mp],
            '00000000'
        );

        $this->assertSame([$macro], $piernaMacro);
        $this->assertSame([$mp], $piernaMp);
        $this->assertSame(
            [],
            IngresoEgresoImputacionDiariaSupport::elegirFilasTesmovPierna([$mp], '00000127')
        );
    }

    public function test_tra_cuadra_con_maximo_de_piernas(): void
    {
        $eval = IngresoEgresoImputacionDiariaSupport::evaluar(
            714000000.0,
            0.0,
            357000000.0,
            357000000.0,
            714000000.0,
            357000000.0,
            357000000.0,
            357000000.0,
            357000000.0,
            true,
            true,
            true,
            true,
            true,
            'TRA',
            0,
            0,
            0
        );

        $this->assertTrue($eval['ok']);
        $this->assertSame([], $eval['alertas']);
    }

    public function test_egr_dolares_pasa_a_pesos_caja_asiento_tesmov_y_ctamov(): void
    {
        $caja = IngresoEgresoImputacionDiariaSupport::aPesos(63.43, 2, 1490);
        $this->assertSame(94510.7, $caja);

        $balance = IngresoEgresoImputacionDiariaSupport::balanceAsientoEnPesos([
            (object) ['monto' => -63.43, 'moneda_id' => 2, 'cotizacion' => 1490],
            (object) ['monto' => 0.98, 'moneda_id' => 2, 'cotizacion' => 1490],
            (object) ['monto' => 62.45, 'moneda_id' => 2, 'cotizacion' => 1490],
        ]);
        $this->assertTrue($balance['balanceado']);
        $this->assertSame(94510.7, $balance['total_debe']);
        $this->assertSame(94510.7, $balance['total_haber']);

        $pesosSinCotizacion = IngresoEgresoImputacionDiariaSupport::balanceAsientoEnPesos([
            (object) ['monto' => 1000, 'moneda_id' => 1, 'cotizacion' => 1490],
            (object) ['monto' => -1000, 'moneda_id' => 1, 'cotizacion' => 1490],
        ]);
        $this->assertSame(1000.0, $pesosSinCotizacion['total_debe']);

        $tesmov = IngresoEgresoImputacionDiariaSupport::tesmovImporteEnPesos((object) [
            'tesv_importe' => 63.43,
            'tesv_cod_mon' => 2,
            'tesv_cotizacion' => 1490,
        ]);
        $this->assertSame(94510.7, $tesmov);

        $ctamov = IngresoEgresoImputacionDiariaSupport::totalesCtamovEnPesos([
            (object) ['ctav_d_h' => 'H', 'ctav_importe' => 63.43, 'ctav_cod_mon' => '2', 'ctav_cotizacion' => 1490],
            (object) ['ctav_d_h' => 'D', 'ctav_importe' => 63.43, 'ctav_cod_mon' => '2', 'ctav_cotizacion' => 1490],
        ]);
        $this->assertSame(94510.7, $ctamov['total_debe']);
        $this->assertSame(94510.7, $ctamov['total_haber']);
    }

    public function test_egr_desvio_caja_asiento_y_tesmov(): void
    {
        $eval = IngresoEgresoImputacionDiariaSupport::evaluar(
            500.0,
            100.0,
            900.0,
            600.0,
            400.0,
            900.0,
            900.0,
            900.0,
            900.0,
            true,
            true,
            true,
            true,
            true,
            'EGR',
            1,
            0,
            0
        );

        $this->assertFalse($eval['ok']);
        $this->assertContains('Caja ≠ asiento', $eval['alertas']);
        $this->assertContains('Caja ≠ tesmov', $eval['alertas']);
    }

    public function test_faltantes_y_cheque(): void
    {
        $eval = IngresoEgresoImputacionDiariaSupport::evaluar(
            100.0,
            50.0,
            0.0,
            150.0,
            0.0,
            0.0,
            0.0,
            0.0,
            0.0,
            false,
            true,
            false,
            false,
            false,
            'TRA',
            2,
            1,
            2
        );

        $this->assertFalse($eval['ok']);
        $this->assertContains('Sin asiento', $eval['alertas']);
        $this->assertContains('Sin tesmov Anita', $eval['alertas']);
        $this->assertContains('Sin ctamov Anita', $eval['alertas']);
        $this->assertContains('Sin pago Anita', $eval['alertas']);
        $this->assertContains('Cheque sin cpromae', $eval['alertas']);
        $this->assertContains('Cheque sin tesmov CHP', $eval['alertas']);
    }

    public function test_asiento_desbalanceado_y_ctamov_distinto(): void
    {
        $eval = IngresoEgresoImputacionDiariaSupport::evaluar(
            100.0,
            0.0,
            100.0,
            100.0,
            100.0,
            90.0,
            100.0,
            100.0,
            80.0,
            true,
            false,
            true,
            true,
            true,
            'ING',
            0,
            0,
            0
        );

        $this->assertFalse($eval['ok']);
        $this->assertContains('Asiento desbalanceado', $eval['alertas']);
        $this->assertContains('Asiento ≠ ctamov', $eval['alertas']);
    }
}

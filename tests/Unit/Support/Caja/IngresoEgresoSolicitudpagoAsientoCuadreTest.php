<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\IngresoEgresoSolicitudpagoSupport;
use PHPUnit\Framework\TestCase;

class IngresoEgresoSolicitudpagoAsientoCuadreTest extends TestCase
{
    public function test_escala_debe_no_banco_al_importe_real_de_caja(): void
    {
        $sinBanco = [[
            'cuentacontable_id' => 419,
            'codigo' => '214010029',
            'debe' => 607652.99,
            'haber' => '',
        ]];
        $caja = [[
            'cuentacontable_id' => 48,
            'codigo' => '111050016',
            'debe' => '',
            'haber' => 607975.30,
        ]];

        $ajustado = IngresoEgresoSolicitudpagoSupport::ajustarLineasNoBancoAlCaja($sinBanco, $caja);

        $this->assertEqualsWithDelta(607975.30, (float) $ajustado[0]['debe'], 0.001);
    }

    public function test_conserva_retencion_haber_y_solo_ajusta_el_debe(): void
    {
        $sinBanco = [
            ['cuentacontable_id' => 1, 'codigo' => '521010001', 'debe' => 995.00, 'haber' => ''],
            ['cuentacontable_id' => 2, 'codigo' => '214010001', 'debe' => '', 'haber' => 50.00],
        ];
        $caja = [[
            'cuentacontable_id' => 3,
            'codigo' => '111050016',
            'debe' => '',
            'haber' => 1000.00,
        ]];

        $ajustado = IngresoEgresoSolicitudpagoSupport::ajustarLineasNoBancoAlCaja($sinBanco, $caja);

        $this->assertEqualsWithDelta(50.00, (float) $ajustado[1]['haber'], 0.001);
        $this->assertEqualsWithDelta(1050.00, (float) $ajustado[0]['debe'], 0.001);
    }

    public function test_no_toca_si_ya_cierra(): void
    {
        $sinBanco = [[
            'cuentacontable_id' => 419,
            'codigo' => '214010029',
            'debe' => 619721.42,
            'haber' => '',
        ]];
        $caja = [[
            'cuentacontable_id' => 48,
            'codigo' => '111050016',
            'debe' => '',
            'haber' => 619721.42,
        ]];

        $ajustado = IngresoEgresoSolicitudpagoSupport::ajustarLineasNoBancoAlCaja($sinBanco, $caja);

        $this->assertEqualsWithDelta(619721.42, (float) $ajustado[0]['debe'], 0.001);
    }

    public function test_pisar_banco_de_la_sp_con_cuenta_financiera_elegida(): void
    {
        // OP 125043: asiento SP = Macro CABA; IE = cuenta 127 Macro Gerli.
        $asientoSp = [
            [
                'cuentacontable_id' => 385,
                'codigo' => '213010023',
                'debe' => 470042.58,
                'haber' => '',
            ],
            [
                'cuentacontable_id' => 48,
                'codigo' => '111050016',
                'debe' => '',
                'haber' => 470042.58,
            ],
        ];
        $caja = [[
            'cuentacontable_id' => 41,
            'codigo' => '111050009',
            'debe' => '',
            'haber' => 470042.58,
        ]];

        $ajustado = IngresoEgresoSolicitudpagoSupport::reemplazarPiernaFinanciera($asientoSp, $caja);

        $this->assertCount(2, $ajustado);
        $this->assertSame(385, (int) $ajustado[0]['cuentacontable_id']);
        $this->assertSame('213010023', $ajustado[0]['codigo']);
        $this->assertEqualsWithDelta(470042.58, (float) $ajustado[0]['debe'], 0.001);
        $this->assertSame(41, (int) $ajustado[1]['cuentacontable_id']);
        $this->assertSame('111050009', $ajustado[1]['codigo']);
        $this->assertEqualsWithDelta(470042.58, (float) $ajustado[1]['haber'], 0.001);
        $codigos = array_column($ajustado, 'codigo');
        $this->assertNotContains('111050016', $codigos);
    }

    public function test_sin_cuenta_caja_conserva_el_asiento_de_la_sp(): void
    {
        $asientoSp = [[
            'cuentacontable_id' => 48,
            'codigo' => '111050016',
            'debe' => '',
            'haber' => 100.00,
        ]];

        $this->assertSame(
            $asientoSp,
            IngresoEgresoSolicitudpagoSupport::reemplazarPiernaFinanciera($asientoSp, [])
        );
    }
}

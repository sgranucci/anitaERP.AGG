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
}

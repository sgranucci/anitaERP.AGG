<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\CobranzaMontosAjusteSupport;
use Tests\TestCase;

final class CobranzaMontosAjusteSupportTest extends TestCase
{
    public function test_ajusta_centavo_en_ultimo_medio_dentro_de_tolerancia(): void
    {
        $medios = [
            ['cuentacaja_id' => 1, 'moneda_id' => 1, 'monto' => 12200.01, 'cotizacion' => 1., 'observacion' => ''],
        ];

        $ajustados = CobranzaMontosAjusteSupport::ajustarMediosPagoAlTotal($medios, 12200.);

        $this->assertSame(12200., round((float) $ajustados[0]['monto'], 2));
    }

    public function test_ajusta_varios_medios_al_total(): void
    {
        $medios = [
            ['cuentacaja_id' => 1, 'moneda_id' => 1, 'monto' => 100.01, 'cotizacion' => 1., 'observacion' => ''],
            ['cuentacaja_id' => 2, 'moneda_id' => 1, 'monto' => 200.01, 'cotizacion' => 1., 'observacion' => ''],
        ];

        $ajustados = CobranzaMontosAjusteSupport::ajustarMediosPagoAlTotal($medios, 300.);

        $this->assertSame(300., round(array_sum(array_column($ajustados, 'monto')), 2));
    }

    public function test_no_ajusta_si_diferencia_supera_tolerancia(): void
    {
        $medios = [
            ['cuentacaja_id' => 1, 'moneda_id' => 1, 'monto' => 100., 'cotizacion' => 1., 'observacion' => ''],
        ];

        $ajustados = CobranzaMontosAjusteSupport::ajustarMediosPagoAlTotal($medios, 100.05);

        $this->assertSame(100., round((float) $ajustados[0]['monto'], 2));
    }
}

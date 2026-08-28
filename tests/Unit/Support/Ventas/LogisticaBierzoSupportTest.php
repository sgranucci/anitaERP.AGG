<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\LogisticaBierzoSupport;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD). Contrasta a-comprob.c calcula():
 * tot_logistica = (tot_grav + _tot_grav_otasa) * clim_logistica / 100
 */
final class LogisticaBierzoSupportTest extends TestCase
{
    public function test_uno_punto_cinco_sobre_gravado_total_no_ultimo_renglon(): void
    {
        $netos = [
            ['concepto' => 'Gravado al 21%', 'tasa' => 21, 'importe' => 3263620.47],
        ];

        $gravado = LogisticaBierzoSupport::gravadoDesdeNetos($netos);

        $this->assertSame(3263620.47, $gravado);
        $this->assertSame(48954.31, LogisticaBierzoSupport::importe($gravado, 1.5));
    }

    public function test_ultimo_renglon_no_es_la_base(): void
    {
        $this->assertSame(1339.27, LogisticaBierzoSupport::importe(89284.99, 1.5));
        $this->assertSame(48954.31, LogisticaBierzoSupport::importe(3263620.47, 1.5));
    }

    public function test_incluye_gravado_otra_tasa_y_excluye_exento(): void
    {
        $netos = [
            ['concepto' => 'Exento', 'tasa' => 0, 'importe' => 1000.0],
            ['concepto' => 'Gravado al 21%', 'tasa' => 21, 'importe' => 800.0],
            ['concepto' => 'Gravado al 10.5%', 'tasa' => 10.5, 'importe' => 200.0],
        ];

        $this->assertSame(1000.0, LogisticaBierzoSupport::gravadoDesdeNetos($netos));
        $this->assertSame(15.0, LogisticaBierzoSupport::importe(1000.0, 1.5));
    }

    public function test_no_suma_la_propia_logistica(): void
    {
        $netos = [
            ['concepto' => 'Gravado al 21%', 'tasa' => 21, 'importe' => 1000.0],
            ['concepto' => 'Total Logistica', 'tasa' => 21, 'importe' => 15.0],
        ];

        $this->assertSame(1000.0, LogisticaBierzoSupport::gravadoDesdeNetos($netos));
    }

    public function test_cero_si_no_hay_porcentaje_ni_gravado(): void
    {
        $this->assertSame(0.0, LogisticaBierzoSupport::importe(1000.0, 0));
        $this->assertSame(0.0, LogisticaBierzoSupport::importe(0, 1.5));
    }
}

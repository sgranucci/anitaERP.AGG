<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\RendicionMaquina\RendicionMaquinaContextoBuilder;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaValoresCuentacajaSupport;
use PHPUnit\Framework\TestCase;

class RendicionMaquinaValoresCuentacajaSupportTest extends TestCase
{
    public function test_moneda_extranjera_es_id_mayor_a_uno(): void
    {
        $this->assertFalse(RendicionMaquinaValoresCuentacajaSupport::esMonedaExtranjera(1));
        $this->assertFalse(RendicionMaquinaValoresCuentacajaSupport::esMonedaExtranjera(0));
        $this->assertTrue(RendicionMaquinaValoresCuentacajaSupport::esMonedaExtranjera(2));
    }

    public function test_monto_en_pesos_multiplica_solo_moneda_extranjera(): void
    {
        $this->assertSame(100.0, RendicionMaquinaValoresCuentacajaSupport::montoEnPesos(1, 100.0, 1200.0));
        $this->assertSame(120000.0, RendicionMaquinaValoresCuentacajaSupport::montoEnPesos(2, 100.0, 1200.0));
    }

    public function test_cotizacion_uno_no_es_usable_en_moneda_extranjera(): void
    {
        $this->assertFalse(RendicionMaquinaValoresCuentacajaSupport::cotizacionUsable(1.0, 2));
        $this->assertFalse(RendicionMaquinaValoresCuentacajaSupport::cotizacionUsable(0.0, 2));
        $this->assertFalse(RendicionMaquinaValoresCuentacajaSupport::cotizacionUsable(null, 2));
        $this->assertTrue(RendicionMaquinaValoresCuentacajaSupport::cotizacionUsable(1200.5, 2));
        $this->assertTrue(RendicionMaquinaValoresCuentacajaSupport::cotizacionUsable(1.0, 1));
    }

    public function test_totales_convierten_por_moneda_de_cuentacaja_no_valormae(): void
    {
        $totales = RendicionMaquinaContextoBuilder::armarValoresTotales([
            ['cuentacaja_id' => 10, 'moneda_id' => 1, 'monto' => 500.0, 'cotizacion' => 1, 'tipo_valormae' => '1'],
            ['cuentacaja_id' => 20, 'moneda_id' => 2, 'monto' => 100.0, 'cotizacion' => 1200.0],
            ['cuentacaja_id' => 30, 'moneda_id' => 1, 'monto' => 80.0, 'cotizacion' => 1, 'nombre' => 'TotalCoin QR Maquina'],
        ]);

        $this->assertSame(120580.0, $totales['valores.total']);
        $this->assertSame(120000.0, $totales['valores.total_divisa']);
        $this->assertSame(500.0, $totales['valores.total_efectivo']);
        $this->assertSame(80.0, $totales['valores.total_qr']);
        $this->assertSame(580.0, $totales['valores.total_no_divisa']);
    }
}

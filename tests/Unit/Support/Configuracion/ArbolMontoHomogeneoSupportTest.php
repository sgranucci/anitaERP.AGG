<?php

namespace Tests\Unit\Support\Configuracion;

use App\Support\Configuracion\ArbolMontoHomogeneoSupport;
use PHPUnit\Framework\TestCase;

class ArbolMontoHomogeneoSupportTest extends TestCase
{
    public function test_misma_moneda_no_convierte(): void
    {
        $this->assertSame(6600.0, ArbolMontoHomogeneoSupport::convertir(6600.0, 2, 2, null));
        $this->assertSame(6600.0, ArbolMontoHomogeneoSupport::convertir(6600.0, 1, 1, ['cotizacionventa' => 1515]));
    }

    public function test_dolares_a_pesos_usa_cotizacion(): void
    {
        $pesos = ArbolMontoHomogeneoSupport::convertir(6600.0, 2, 1, [
            'cotizacionventa' => 1515.0,
            'cotizacioncompra' => 1465.0,
        ]);

        $this->assertSame(9999000.0, $pesos);
    }

    public function test_sin_cotizacion_no_asume_uno_a_uno(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No se asume 1:1');

        ArbolMontoHomogeneoSupport::convertir(6600.0, 2, 1, [
            'cotizacionventa' => 0,
            'cotizacioncompra' => 0,
        ]);
    }
}

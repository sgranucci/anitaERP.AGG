<?php

namespace Tests\Unit\Support\Ventas\Waitry;

use App\Support\Ventas\Waitry\WaitryOpcionalesPendientesSupport;
use PHPUnit\Framework\TestCase;

class WaitryOpcionalesPendientesSupportTest extends TestCase
{
    public function test_normalizar_conserva_cantidad_mayor_a_uno(): void
    {
        $lineas = WaitryOpcionalesPendientesSupport::normalizarLineas([
            [
                'sku' => 'V0647',
                'titulo' => 'Café con leche + 2 medialunas',
                'cantidad' => 2,
                'precio_unitario' => 5400,
            ],
        ]);

        $this->assertCount(1, $lineas);
        $this->assertSame('V0647', $lineas[0]['sku']);
        $this->assertSame(2.0, $lineas[0]['cantidad']);
        $this->assertSame(5400.0, $lineas[0]['precio_unitario']);
        $this->assertSame(['V0647 ×2'], WaitryOpcionalesPendientesSupport::etiquetasParaAviso($lineas));
    }

    public function test_desde_errores_parsea_sufijo_cantidad(): void
    {
        $lineas = WaitryOpcionalesPendientesSupport::desdeErrores([
            'SKU «V0647» (Café con leche + 2 medialunas) ×2 requiere opcionales de fórmula: '
            .'no se importa desde Waitry. Carguelo manualmente en el POS (modal de opcionales).',
        ]);

        $this->assertCount(1, $lineas);
        $this->assertSame('V0647', $lineas[0]['sku']);
        $this->assertSame(2.0, $lineas[0]['cantidad']);
        $this->assertSame('Café con leche + 2 medialunas', $lineas[0]['titulo']);
    }

    public function test_desde_errores_sin_cantidad_asume_uno(): void
    {
        $lineas = WaitryOpcionalesPendientesSupport::desdeErrores([
            'SKU «V0123» (Algo) requiere opcionales de fórmula: no se importa desde Waitry.',
        ]);

        $this->assertSame(1.0, $lineas[0]['cantidad']);
        $this->assertSame('V0123', $lineas[0]['sku']);
    }
}

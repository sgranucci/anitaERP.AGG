<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorBorradorPendienteSupport;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorBorradorPendienteSupportTest extends TestCase
{
    public function test_sin_facturas_no_hay_pendientes(): void
    {
        $this->assertFalse(ComprobanteProveedorBorradorPendienteSupport::hayPendientes([
            'cantidad' => 0,
            'facturas' => [],
        ]));
        $this->assertSame('(ninguna)', ComprobanteProveedorBorradorPendienteSupport::formatearLista([], 0));
    }

    public function test_formatea_lista_y_recorte(): void
    {
        $items = [[
            'id' => 248,
            'empresa' => 'KANDIKO S.A.',
            'comprobante' => 'FGA A 0006-481',
            'proveedor' => 'RIPOLL AGUSTIN',
            'fecha_iva' => '25/08/2026',
            'total' => '60.500,00',
            'antiguedad' => '2 días',
            'usuario' => 'Federico Rodriguez',
        ]];

        $texto = ComprobanteProveedorBorradorPendienteSupport::formatearLista($items, 3);

        $this->assertStringContainsString('#248 | KANDIKO S.A. | FGA A 0006-481 | RIPOLL AGUSTIN', $texto);
        $this->assertStringContainsString('$ 60.500,00', $texto);
        $this->assertStringContainsString('Cargó Federico Rodriguez', $texto);
        $this->assertStringContainsString('… y 2 más', $texto);
        $this->assertTrue(ComprobanteProveedorBorradorPendienteSupport::hayPendientes(['cantidad' => 3]));
    }
}

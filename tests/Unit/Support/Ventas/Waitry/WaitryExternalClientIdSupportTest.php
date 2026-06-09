<?php

namespace Tests\Unit\Support\Ventas\Waitry;

use App\Support\Ventas\Waitry\WaitryExternalClientIdSupport;
use Tests\TestCase;

final class WaitryExternalClientIdSupportTest extends TestCase
{
    public function test_desde_factura_extrae_punto_y_numero(): void
    {
        $this->assertSame(
            '00003-123456',
            WaitryExternalClientIdSupport::desdeFactura('FC A 00003-123456', 99)
        );
    }

    public function test_desde_factura_vacia_usa_venta_id(): void
    {
        $this->assertSame('V42', WaitryExternalClientIdSupport::desdeFactura('', 42));
    }
}

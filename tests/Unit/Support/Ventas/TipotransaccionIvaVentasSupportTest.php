<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\TipotransaccionIvaVentasSupport;
use PHPUnit\Framework\TestCase;

final class TipotransaccionIvaVentasSupportTest extends TestCase
{
    public function test_marca_activa_acepta_valores_tipicos(): void
    {
        $this->assertTrue(TipotransaccionIvaVentasSupport::marcaActiva(true));
        $this->assertTrue(TipotransaccionIvaVentasSupport::marcaActiva(1));
        $this->assertTrue(TipotransaccionIvaVentasSupport::marcaActiva('1'));
        $this->assertFalse(TipotransaccionIvaVentasSupport::marcaActiva(false));
        $this->assertFalse(TipotransaccionIvaVentasSupport::marcaActiva(0));
        $this->assertFalse(TipotransaccionIvaVentasSupport::marcaActiva(null));
        $this->assertFalse(TipotransaccionIvaVentasSupport::vaAlIvaVentas(null));
    }
}

<?php

namespace Tests\Unit\Models\Ventas;

use App\Models\Ventas\Tipotransaccion;
use PHPUnit\Framework\TestCase;

class TipotransaccionCorrespondeRemitoTest extends TestCase
{
    public function test_fac_y_fce_si_nc_no(): void
    {
        $this->assertTrue((new Tipotransaccion(['abreviatura' => 'FAC', 'operacion' => 'V']))->correspondeRemito());
        $this->assertTrue((new Tipotransaccion(['abreviatura' => 'FCE', 'operacion' => 'V']))->correspondeRemito());
        $this->assertFalse((new Tipotransaccion(['abreviatura' => 'NCD', 'operacion' => 'C']))->correspondeRemito());
        $this->assertFalse((new Tipotransaccion(['abreviatura' => 'NCE', 'operacion' => 'C']))->correspondeRemito());
        $this->assertFalse((new Tipotransaccion(['abreviatura' => 'NCG', 'operacion' => 'C']))->correspondeRemito());
        $this->assertFalse((new Tipotransaccion(['abreviatura' => 'FAU', 'operacion' => 'U']))->correspondeRemito());
        $this->assertFalse((new Tipotransaccion(['abreviatura' => 'RMV', 'operacion' => 'V']))->correspondeRemito());
    }
}

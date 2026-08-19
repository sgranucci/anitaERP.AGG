<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\Flash\FlashCajaValidacionSupport;
use Tests\TestCase;

class FlashCajaValidacionSupportTest extends TestCase
{
    public function test_mbarrios_puede_validar(): void
    {
        $this->assertTrue(FlashCajaValidacionSupport::usuarioPuedeValidar('mbarrios'));
        $this->assertTrue(FlashCajaValidacionSupport::usuarioPuedeValidar('MBarrios'));
    }

    public function test_otro_usuario_no_puede_validar(): void
    {
        $this->assertFalse(FlashCajaValidacionSupport::usuarioPuedeValidar('gcorbetta'));
        $this->assertFalse(FlashCajaValidacionSupport::usuarioPuedeValidar(''));
        $this->assertFalse(FlashCajaValidacionSupport::usuarioPuedeValidar('administrador'));
    }
}

<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\Flash\FlashCajaValidacionSupport;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class FlashCajaValidacionSupportTest extends TestCase
{
    public function test_mbarrios_puede_validar(): void
    {
        $this->assertTrue(FlashCajaValidacionSupport::usuarioPuedeValidar('mbarrios'));
        $this->assertTrue(FlashCajaValidacionSupport::usuarioPuedeValidar('MBarrios'));
    }

    public function test_sergio_y_admin_pueden_validar(): void
    {
        $this->assertTrue(FlashCajaValidacionSupport::usuarioPuedeValidar('sergio'));
        $this->assertTrue(FlashCajaValidacionSupport::usuarioPuedeValidar('admin'));
    }

    public function test_otro_usuario_no_puede_validar(): void
    {
        $this->assertFalse(FlashCajaValidacionSupport::usuarioPuedeValidar('gcorbetta'));
        $this->assertFalse(FlashCajaValidacionSupport::usuarioPuedeValidar(''));
        $this->assertFalse(FlashCajaValidacionSupport::usuarioPuedeValidar('administrador'));
    }

    public function test_rol_administrador_en_sesion_puede_validar(): void
    {
        Session::put('rol_nombre', 'administrador');
        Session::put('usuario', 'otro');

        $this->assertTrue(FlashCajaValidacionSupport::usuarioPuedeValidar());
    }
}

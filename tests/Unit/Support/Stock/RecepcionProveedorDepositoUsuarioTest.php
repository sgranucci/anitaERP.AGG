<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\RecepcionProveedorDepositoSupport;
use App\Support\Stock\UsuarioDepositoAutorizado;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class RecepcionProveedorDepositoUsuarioTest extends TestCase
{
    protected function tearDown(): void
    {
        Session::forget('usuario_depositos_ids');
        parent::tearDown();
    }

    public function test_deposito_entrega_visible_sin_restriccion_usuario(): void
    {
        Session::forget('usuario_depositos_ids');

        $this->assertNull(RecepcionProveedorDepositoSupport::depositoEntregaVisible(null, 1));
        $this->assertNull(RecepcionProveedorDepositoSupport::depositoEntregaVisible(0, 1));
    }

    public function test_usuario_sin_depositos_cargados_no_restringe(): void
    {
        Session::forget('usuario_depositos_ids');

        $this->assertNull(UsuarioDepositoAutorizado::idsRestringidos());
        $this->assertTrue(UsuarioDepositoAutorizado::depositoAutorizado(99));
    }

    public function test_usuario_con_depositos_solo_autoriza_los_asignados(): void
    {
        Session::put('usuario_depositos_ids', [3, 7]);

        $this->assertSame([3, 7], UsuarioDepositoAutorizado::idsRestringidos());
        $this->assertTrue(UsuarioDepositoAutorizado::depositoAutorizado(3));
        $this->assertFalse(UsuarioDepositoAutorizado::depositoAutorizado(99));
    }
}

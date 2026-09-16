<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\UsuarioDepositoAutorizado;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class UsuarioDepositoAutorizadoArticuloFiltroTest extends TestCase
{
    protected function tearDown(): void
    {
        Session::forget('usuario_depositos_ids');
        parent::tearDown();
    }

    public function test_sin_restriccion_no_filtra_articulos(): void
    {
        Session::forget('usuario_depositos_ids');

        $this->assertNull(UsuarioDepositoAutorizado::idsParaFiltroArticulo());
        $this->assertTrue(UsuarioDepositoAutorizado::articuloAutorizadoPorDepositoEntrega(999));
        $this->assertTrue(UsuarioDepositoAutorizado::articuloAutorizadoPorDepositoEntrega(null));
    }

    public function test_con_restriccion_solo_autoriza_depositoentrega_asignado(): void
    {
        Session::put('usuario_depositos_ids', [30, 32]);

        $ids = UsuarioDepositoAutorizado::idsParaFiltroArticulo();
        $this->assertIsArray($ids);
        $this->assertContains(30, $ids);
        $this->assertContains(32, $ids);

        $this->assertTrue(UsuarioDepositoAutorizado::articuloAutorizadoPorDepositoEntrega(30));
        $this->assertFalse(UsuarioDepositoAutorizado::articuloAutorizadoPorDepositoEntrega(999));
        $this->assertFalse(UsuarioDepositoAutorizado::articuloAutorizadoPorDepositoEntrega(null));
    }

    public function test_deposito_fijo_no_autorizado_devuelve_vacio(): void
    {
        Session::put('usuario_depositos_ids', [30, 32]);

        $this->assertSame([], UsuarioDepositoAutorizado::idsParaFiltroArticulo(999));
    }
}

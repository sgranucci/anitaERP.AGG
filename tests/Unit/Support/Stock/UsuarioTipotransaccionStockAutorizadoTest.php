<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\UsuarioTipotransaccionStockAutorizado;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class UsuarioTipotransaccionStockAutorizadoTest extends TestCase
{
    protected function tearDown(): void
    {
        Session::forget('usuario_tipotransaccion_stock_ids');
        parent::tearDown();
    }

    public function test_usuario_sin_tipos_cargados_no_restringe(): void
    {
        Session::forget('usuario_tipotransaccion_stock_ids');

        $this->assertNull(UsuarioTipotransaccionStockAutorizado::idsRestringidos());
        $this->assertTrue(UsuarioTipotransaccionStockAutorizado::tipotransaccionAutorizada(99));
    }

    public function test_usuario_con_tipos_solo_autoriza_los_asignados(): void
    {
        Session::put('usuario_tipotransaccion_stock_ids', [2, 5]);

        $this->assertSame([2, 5], UsuarioTipotransaccionStockAutorizado::idsRestringidos());
        $this->assertTrue(UsuarioTipotransaccionStockAutorizado::tipotransaccionAutorizada(2));
        $this->assertFalse(UsuarioTipotransaccionStockAutorizado::tipotransaccionAutorizada(99));
    }
}

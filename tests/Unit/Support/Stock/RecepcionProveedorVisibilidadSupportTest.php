<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\RecepcionProveedorVisibilidadSupport;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class RecepcionProveedorVisibilidadSupportTest extends TestCase
{
    protected function tearDown(): void
    {
        Session::forget('usuario_empresas');
        parent::tearDown();
    }

    public function test_empresa_ids_asignadas_desde_sesion(): void
    {
        Session::put('usuario_empresas', [
            ['id' => 2, 'nombre' => 'Empresa B'],
            ['id' => 5, 'nombre' => 'Empresa E'],
        ]);

        $this->assertSame([2, 5], RecepcionProveedorVisibilidadSupport::empresaIdsAsignadas());
    }

    public function test_centrocosto_filtro_null_sin_usuario_autenticado(): void
    {
        $this->assertNull(RecepcionProveedorVisibilidadSupport::centrocostoFiltroUsuario());
    }
}

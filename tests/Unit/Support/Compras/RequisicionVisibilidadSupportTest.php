<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\RequisicionVisibilidadSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class RequisicionVisibilidadSupportTest extends TestCase
{
    protected function tearDown(): void
    {
        Session::forget('usuario_empresas');
        Session::forget('rol_nombre');
        parent::tearDown();
    }

    public function test_empresa_ids_asignadas_desde_sesion(): void
    {
        Session::put('usuario_empresas', [
            ['id' => 1, 'nombre' => 'Biyemas'],
            ['id' => 3, 'nombre' => 'Rebisco'],
        ]);

        $this->assertSame([1, 3], RequisicionVisibilidadSupport::empresaIdsAsignadas());
    }

    public function test_administrador_no_restringe_el_informe_con_alias(): void
    {
        Session::put('rol_nombre', 'administrador');

        $query = DB::table('requisicion as r');
        RequisicionVisibilidadSupport::aplicarFiltroListado($query, 'r');

        $sql = $query->toSql();
        $this->assertStringNotContainsString('creousuario_id', $sql);
        $this->assertStringNotContainsString('centrocosto_id', $sql);
        $this->assertStringNotContainsString('empresa_id', $sql);
    }
}

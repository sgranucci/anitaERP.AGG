<?php

namespace Tests\Unit\Support\Seguridad;

use App\Support\Seguridad\IngresoProveedorVisibilidadSupport;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IngresoProveedorVisibilidadEmpresaTest extends TestCase
{
    public function test_sin_empresas_no_recorta_la_bandeja(): void
    {
        $query = DB::table('ingreso_proveedor')->where('estado', 'PENDIENTE');
        IngresoProveedorVisibilidadSupport::aplicarEmpresas($query, []);

        $this->assertSame(['PENDIENTE'], $query->getBindings());
        $this->assertStringNotContainsString('empresa_id', $query->toSql());
    }

    public function test_bandeja_queda_en_el_establecimiento_asignado(): void
    {
        $query = DB::table('ingreso_proveedor')->where('estado', 'PENDIENTE');
        IngresoProveedorVisibilidadSupport::aplicarEmpresas($query, [2, 0, 2]);

        $this->assertStringContainsString('empresa_id', $query->toSql());
        $this->assertSame(['PENDIENTE', 2], $query->getBindings());
    }
}

<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\OrdencompraSectorVisibilidadSupport;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrdencompraSectorVisibilidadSupportTest extends TestCase
{
    public function test_ve_todos_no_restringe_sector(): void
    {
        $this->assertNull(OrdencompraSectorVisibilidadSupport::idSectorParaFiltro(12, true));
        $this->assertNull(OrdencompraSectorVisibilidadSupport::idSectorParaFiltro(null, true));
    }

    public function test_sin_ver_todos_usa_el_sector_del_usuario(): void
    {
        $this->assertSame(12, OrdencompraSectorVisibilidadSupport::idSectorParaFiltro(12, false));
    }

    public function test_sin_ver_todos_y_sin_sector_no_ve_nada(): void
    {
        $this->assertSame(0, OrdencompraSectorVisibilidadSupport::idSectorParaFiltro(null, false));
        $this->assertSame(0, OrdencompraSectorVisibilidadSupport::idSectorParaFiltro(0, false));
    }

    public function test_aplicar_filtro_con_id_cero_deja_la_consulta_vacia(): void
    {
        $query = DB::table('ordencompra');
        OrdencompraSectorVisibilidadSupport::aplicarFiltroConId(
            $query,
            'ordencompra.sector_legajocompra_id',
            0
        );

        $this->assertStringContainsString('1 = 0', $query->toSql());
    }

    public function test_aplicar_filtro_con_sector_restringe_la_columna(): void
    {
        $query = DB::table('ordencompra');
        OrdencompraSectorVisibilidadSupport::aplicarFiltroConId(
            $query,
            'ordencompra.sector_legajocompra_id',
            7
        );

        $sql = $query->toSql();
        $this->assertStringContainsString('sector_legajocompra_id', $sql);
        $this->assertSame([7], $query->getBindings());
    }

    public function test_aplicar_filtro_nulo_no_agrega_recorte(): void
    {
        $query = DB::table('ordencompra');
        OrdencompraSectorVisibilidadSupport::aplicarFiltroConId(
            $query,
            'ordencompra.sector_legajocompra_id',
            null
        );

        $this->assertStringNotContainsString('sector_legajocompra_id', $query->toSql());
        $this->assertStringNotContainsString('1 = 0', $query->toSql());
    }
}

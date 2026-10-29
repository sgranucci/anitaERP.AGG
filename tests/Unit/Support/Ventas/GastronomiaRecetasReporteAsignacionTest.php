<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\GastronomiaRecetasReporteAsignacion;
use PHPUnit\Framework\TestCase;

final class GastronomiaRecetasReporteAsignacionTest extends TestCase
{
    public function test_articulo_activo_entra_solo_con_filtro_activos(): void
    {
        $articulos = [$this->articulo(10, 'ACTIVO', 1500)];

        $incluido = GastronomiaRecetasReporteAsignacion::resolver($articulos, $this->filtros(activos: true));
        $this->assertTrue($incluido['incluye']);
        $this->assertSame(GastronomiaRecetasReporteAsignacion::ESTADO_ACTIVO, $incluido['estado']);
        $this->assertSame(10, $incluido['articulo']['id']);

        $excluido = GastronomiaRecetasReporteAsignacion::resolver($articulos, $this->filtros(inactivos: true));
        $this->assertFalse($excluido['incluye']);
    }

    public function test_articulo_inactivo_entra_solo_con_filtro_inactivos(): void
    {
        $articulos = [$this->articulo(11, 'INACTIVO', 800)];

        $incluido = GastronomiaRecetasReporteAsignacion::resolver($articulos, $this->filtros(inactivos: true));
        $this->assertTrue($incluido['incluye']);
        $this->assertSame(GastronomiaRecetasReporteAsignacion::ESTADO_INACTIVO, $incluido['estado']);

        $excluido = GastronomiaRecetasReporteAsignacion::resolver($articulos, $this->filtros(activos: true));
        $this->assertFalse($excluido['incluye']);
    }

    public function test_sin_articulo_entra_solo_si_se_piden_no_asignadas(): void
    {
        $incluido = GastronomiaRecetasReporteAsignacion::resolver([], $this->filtros(sinAsignar: true));
        $this->assertTrue($incluido['incluye']);
        $this->assertSame(GastronomiaRecetasReporteAsignacion::ESTADO_SIN_ASIGNAR, $incluido['estado']);
        $this->assertNull($incluido['articulo']);

        $excluido = GastronomiaRecetasReporteAsignacion::resolver([], $this->filtros(activos: true, sinAsignar: false));
        $this->assertFalse($excluido['incluye']);
    }

    public function test_si_hay_un_activo_no_entra_en_el_filtro_de_inactivos(): void
    {
        $articulos = [
            $this->articulo(1, 'ACTIVO', 100),
            $this->articulo(2, 'INACTIVO', 900),
        ];

        $soloInactivos = GastronomiaRecetasReporteAsignacion::resolver($articulos, $this->filtros(inactivos: true));
        $this->assertFalse($soloInactivos['incluye']);

        $activos = GastronomiaRecetasReporteAsignacion::resolver($articulos, $this->filtros(activos: true));
        $this->assertTrue($activos['incluye']);
        $this->assertSame(1, $activos['articulo']['id']);
    }

    public function test_empresa_o_rubro_de_otro_articulo_no_la_vuelve_sin_asignar(): void
    {
        $articulos = [$this->articulo(5, 'ACTIVO', 100, empresaId: 2, categoriaId: 9)];

        $otraEmpresa = GastronomiaRecetasReporteAsignacion::resolver(
            $articulos,
            $this->filtros(activos: true, sinAsignar: true, empresaId: 1),
        );
        $this->assertFalse($otraEmpresa['incluye']);

        $otraCategoria = GastronomiaRecetasReporteAsignacion::resolver(
            $articulos,
            $this->filtros(activos: true, categoriaId: 3),
        );
        $this->assertFalse($otraCategoria['incluye']);
    }

    public function test_entre_activos_prioriza_el_de_mayor_precio(): void
    {
        $articulos = [
            $this->articulo(1, 'ACTIVO', 100),
            $this->articulo(2, 'ACTIVO', 500),
        ];

        $resultado = GastronomiaRecetasReporteAsignacion::resolver($articulos, $this->filtros(activos: true));

        $this->assertSame(2, $resultado['articulo']['id']);
    }

    /**
     * @return array{id:int,estado:string,empresa_id:int,precio:float,descripcion:string,sku:string,categoria_id:int,subcategoria_id:int}
     */
    private function articulo(
        int $id,
        string $estado,
        float $precio,
        int $empresaId = 1,
        int $categoriaId = 4,
        int $subcategoriaId = 7,
    ): array {
        return [
            'id' => $id,
            'estado' => $estado,
            'empresa_id' => $empresaId,
            'precio' => $precio,
            'descripcion' => 'Articulo '.$id,
            'sku' => 'V'.$id,
            'categoria_id' => $categoriaId,
            'subcategoria_id' => $subcategoriaId,
        ];
    }

    /**
     * @return array{activos:bool,inactivos:bool,sin_asignar:bool,empresa_id:int,categoria_id:int,subcategoria_id:int}
     */
    private function filtros(
        bool $activos = false,
        bool $inactivos = false,
        bool $sinAsignar = false,
        int $empresaId = 0,
        int $categoriaId = 0,
        int $subcategoriaId = 0,
    ): array {
        return [
            'activos' => $activos,
            'inactivos' => $inactivos,
            'sin_asignar' => $sinAsignar,
            'empresa_id' => $empresaId,
            'categoria_id' => $categoriaId,
            'subcategoria_id' => $subcategoriaId,
        ];
    }
}

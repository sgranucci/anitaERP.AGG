<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ProveedorAnitaGeoSupport;
use PHPUnit\Framework\TestCase;

class ProveedorAnitaGeoSupportTest extends TestCase
{
    /** @var list<array{id:int,codigo:string,nombre:string,provincia_id:int}> */
    private array $localidades = [
        ['id' => 2, 'codigo' => '5301', 'nombre' => 'DE LAS CATALINAS', 'provincia_id' => 1],
        ['id' => 5, 'codigo' => '14731', 'nombre' => 'ROJAS RICARDO DR.', 'provincia_id' => 1],
        ['id' => 4072, 'codigo' => '16501', 'nombre' => 'TAPIALES', 'provincia_id' => 2],
        ['id' => 15211, 'codigo' => '5', 'nombre' => 'ALTO DE LOS REARTES', 'provincia_id' => 4],
        ['id' => 23631, 'codigo' => '2', 'nombre' => 'ALTO DE LAS PLUMAS', 'provincia_id' => 7],
        ['id' => 9001, 'codigo' => '9001', 'nombre' => 'BUENOS AIRES CHICO', 'provincia_id' => 7],
    ];

    /** @var list<array{id:int,nombre:string}> */
    private array $provincias = [
        ['id' => 1, 'nombre' => 'Capital Federal'],
        ['id' => 2, 'nombre' => 'Buenos Aires'],
        ['id' => 4, 'nombre' => 'Cordoba'],
        ['id' => 7, 'nombre' => 'Chubut'],
    ];

    public function test_codigo_chico_no_pisa_con_otra_localidad_y_respeta_la_provincia_del_proveedor(): void
    {
        $geo = ProveedorAnitaGeoSupport::resolver(
            $this->localidades,
            $this->provincias,
            '2',
            'DE LAS CATALINA',
            'BUENOS AIRES',
            true,
        );

        $this->assertSame(2, $geo['localidad_id']);
        $this->assertSame(2, $geo['provincia_id']);
    }

    public function test_si_el_texto_no_coincide_con_el_codigo_usa_el_nombre(): void
    {
        $geo = ProveedorAnitaGeoSupport::resolver(
            $this->localidades,
            $this->provincias,
            '5',
            'TAPIALES',
            'BS. AS.',
            true,
        );

        $this->assertSame(4072, $geo['localidad_id']);
        $this->assertSame(2, $geo['provincia_id']);
    }

    public function test_texto_ajeno_al_maestro_no_toma_el_codigo_que_choca(): void
    {
        $geo = ProveedorAnitaGeoSupport::resolver(
            $this->localidades,
            $this->provincias,
            '2',
            'CAP.FED',
            'BS.AS',
            true,
        );

        $this->assertNull($geo['localidad_id']);
        $this->assertSame(2, $geo['provincia_id']);
    }

    public function test_codigo_truncado_de_anita_matchea_por_prefijo_del_id(): void
    {
        $geo = ProveedorAnitaGeoSupport::resolver(
            $this->localidades,
            $this->provincias,
            '5',
            'ROJAS RICARDO D',
            '',
            true,
        );

        $this->assertSame(5, $geo['localidad_id']);
        $this->assertSame(1, $geo['provincia_id']);
    }

    public function test_un_texto_corto_no_arrastra_otra_localidad_por_prefijo(): void
    {
        $geo = ProveedorAnitaGeoSupport::resolver(
            $this->localidades,
            $this->provincias,
            '3',
            'BUENOS AIRES',
            '',
            true,
        );

        $this->assertNull($geo['localidad_id']);
        $this->assertNull($geo['provincia_id']);
    }
}

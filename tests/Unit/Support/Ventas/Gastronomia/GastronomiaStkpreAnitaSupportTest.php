<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\ApiAnita;
use App\Support\Ventas\Gastronomia\GastronomiaStkpreAnitaSupport;
use Mockery;
use Tests\TestCase;

class GastronomiaStkpreAnitaSupportTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_asigna_precio_aun_cuando_el_slot_de_lista_estaba_en_null(): void
    {
        $api = Mockery::mock(ApiAnita::class);
        $api->shouldReceive('apiCall')
            ->once()
            ->andReturn(json_encode([
                [
                    'stkp_articulo' => '00000000V0361',
                    'stkp_lista' => '5006',
                    'stkp_precio' => '1166.18816666667',
                ],
            ]));

        $support = new GastronomiaStkpreAnitaSupport($api);
        $resultado = $support->preciosPorSkusYListas(['V0361'], ['5005', '5006']);

        $this->assertSame(1166.1882, $resultado['V0361']['5006']);
        $this->assertNull($resultado['V0361']['5005']);
    }
}

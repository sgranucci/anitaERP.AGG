<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\RecepcionProveedorRedistribuirAtribucionOcSupport;
use PHPUnit\Framework\TestCase;

class RecepcionProveedorRedistribuirAtribucionOcSupportTest extends TestCase
{
    public function test_redistribuye_com_apilado_en_una_sola_linea(): void
    {
        $lineasOc = [
            ['id' => 14593, 'articulo_id' => 100, 'penvp_orden' => 1, 'penvp_nro_interno' => 942356, 'cantidad' => 12.0],
            ['id' => 14594, 'articulo_id' => 100, 'penvp_orden' => 1, 'penvp_nro_interno' => 942356, 'cantidad' => 12.0],
            ['id' => 14595, 'articulo_id' => 100, 'penvp_orden' => 2, 'penvp_nro_interno' => 942357, 'cantidad' => 12.0],
        ];

        $coms = [[
            'recepcion_id' => 1,
            'numerorecepcion' => 164598,
            'fecha' => '2026-06-26',
            'lineas' => [
                ['rpa_id' => 1, 'ordencompra_articulo_id' => 14593, 'articulo_id' => 100, 'cantidad' => 1.0, 'penvp_orden' => 1, 'penvp_nro_interno' => 942356],
                ['rpa_id' => 2, 'ordencompra_articulo_id' => 14593, 'articulo_id' => 100, 'cantidad' => 1.0, 'penvp_orden' => 1, 'penvp_nro_interno' => 942356],
                ['rpa_id' => 3, 'ordencompra_articulo_id' => 14593, 'articulo_id' => 100, 'cantidad' => 1.0, 'penvp_orden' => 1, 'penvp_nro_interno' => 942356],
            ],
        ]];

        $plan = RecepcionProveedorRedistribuirAtribucionOcSupport::planificar($lineasOc, $coms);

        $this->assertCount(2, $plan['cambios']);
        $this->assertSame(14594, $plan['cambios'][0]['hacia_oc_art']);
        $this->assertSame(14595, $plan['cambios'][1]['hacia_oc_art']);
        $this->assertSame(1, $plan['coms_redistribuidos']);
        $this->assertCount(1, $plan['internos_duplicados']);
        $this->assertSame(942356, $plan['internos_duplicados'][0]['penvp_nro_interno']);
    }

    public function test_no_toca_com_ya_distribuido(): void
    {
        $lineasOc = [
            ['id' => 1, 'articulo_id' => 100, 'penvp_orden' => 1, 'penvp_nro_interno' => 10, 'cantidad' => 12.0],
            ['id' => 2, 'articulo_id' => 100, 'penvp_orden' => 2, 'penvp_nro_interno' => 11, 'cantidad' => 12.0],
        ];
        $coms = [[
            'recepcion_id' => 1,
            'numerorecepcion' => 166514,
            'fecha' => '2026-08-18',
            'lineas' => [
                ['rpa_id' => 1, 'ordencompra_articulo_id' => 1, 'articulo_id' => 100, 'cantidad' => 1.0, 'penvp_orden' => null, 'penvp_nro_interno' => null],
                ['rpa_id' => 2, 'ordencompra_articulo_id' => 2, 'articulo_id' => 100, 'cantidad' => 1.0, 'penvp_orden' => null, 'penvp_nro_interno' => null],
            ],
        ]];

        $plan = RecepcionProveedorRedistribuirAtribucionOcSupport::planificar($lineasOc, $coms);

        $this->assertSame([], $plan['cambios']);
        $this->assertSame(0, $plan['coms_redistribuidos']);
    }

    public function test_ignora_rpa_sin_ordencompra_articulo_id(): void
    {
        $lineasOc = [
            ['id' => 1, 'articulo_id' => 100, 'penvp_orden' => 1, 'penvp_nro_interno' => 10, 'cantidad' => 12.0],
            ['id' => 2, 'articulo_id' => 100, 'penvp_orden' => 2, 'penvp_nro_interno' => 11, 'cantidad' => 12.0],
        ];
        $coms = [[
            'recepcion_id' => 1,
            'numerorecepcion' => 161800,
            'fecha' => '2026-03-13',
            'lineas' => [
                ['rpa_id' => 1, 'ordencompra_articulo_id' => null, 'articulo_id' => 100, 'cantidad' => 1.0, 'penvp_orden' => null, 'penvp_nro_interno' => null],
                ['rpa_id' => 2, 'ordencompra_articulo_id' => null, 'articulo_id' => 100, 'cantidad' => 1.0, 'penvp_orden' => null, 'penvp_nro_interno' => null],
            ],
        ]];

        $plan = RecepcionProveedorRedistribuirAtribucionOcSupport::planificar($lineasOc, $coms);

        $this->assertSame([], $plan['cambios']);
        $this->assertSame(2, $plan['lineas_sin_atribuir']);
    }

    public function test_resolver_por_interno_unico_rechaza_duplicados(): void
    {
        $lineasOc = [
            ['id' => 14593, 'penvp_nro_interno' => 942356],
            ['id' => 14594, 'penvp_nro_interno' => 942356],
            ['id' => 14595, 'penvp_nro_interno' => 942357],
        ];

        $this->assertNull(
            RecepcionProveedorRedistribuirAtribucionOcSupport::resolverLineaPorInternoUnico($lineasOc, 942356)
        );
        $this->assertSame(
            14595,
            RecepcionProveedorRedistribuirAtribucionOcSupport::resolverLineaPorInternoUnico($lineasOc, 942357)
        );
    }
}

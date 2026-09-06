<?php

namespace Tests\Unit\Support\Compras\AnitaImport;

use App\Support\Compras\AnitaImport\ComprasCpRecepcionAnitaBackfillSupport;
use PHPUnit\Framework\TestCase;

class ComprasCpRecepcionAnitaBackfillSupportTest extends TestCase
{
    public function test_resuelve_com_directa(): void
    {
        $cp = (object) [
            'id' => 10,
            'empresa_id' => 1,
            'proveedor_id' => 5,
            'ordencompra_id' => null,
            'proveedor_codigo' => '123',
        ];
        $apl = [(object) [
            'aplp_ref_tipo' => 'COM',
            'aplp_ref_sucursal' => 1,
            'aplp_ref_nro' => 99,
            'aplp_ref_letra' => 'X',
        ]];
        $indice = [
            'por_com' => [
                '1|99' => [(object) ['id' => 500, 'empresa_id' => 1, 'proveedor_id' => 5, 'ordencompra_id' => 7]],
            ],
            'por_com_prov' => [
                '5|1|99' => [(object) ['id' => 500, 'empresa_id' => 1, 'proveedor_id' => 5, 'ordencompra_id' => 7]],
            ],
        ];

        $r = ComprasCpRecepcionAnitaBackfillSupport::resolverCp($cp, $apl, [], $indice);

        $this->assertSame('ok', $r['stat']);
        $this->assertSame('com', $r['via']);
        $this->assertSame(500, $r['recepciones'][0]['id']);
    }

    public function test_omite_si_oc_del_cp_no_coincide(): void
    {
        $cp = (object) [
            'id' => 10,
            'empresa_id' => 1,
            'proveedor_id' => 5,
            'ordencompra_id' => 100,
            'proveedor_codigo' => '123',
        ];
        $apl = [(object) [
            'aplp_ref_tipo' => 'COM',
            'aplp_ref_sucursal' => 1,
            'aplp_ref_nro' => 99,
            'aplp_ref_letra' => 'X',
        ]];
        $indice = [
            'por_com' => [
                '1|99' => [(object) ['id' => 500, 'empresa_id' => 1, 'proveedor_id' => 5, 'ordencompra_id' => 200]],
            ],
            'por_com_prov' => [
                '5|1|99' => [(object) ['id' => 500, 'empresa_id' => 1, 'proveedor_id' => 5, 'ordencompra_id' => 200]],
            ],
        ];

        $r = ComprasCpRecepcionAnitaBackfillSupport::resolverCp($cp, $apl, [], $indice);

        $this->assertSame('ambiguo_oc', $r['stat']);
    }

    public function test_via_pep_usa_hermanas_com(): void
    {
        $cp = (object) [
            'id' => 10,
            'empresa_id' => 1,
            'proveedor_id' => 5,
            'ordencompra_id' => null,
            'proveedor_codigo' => '000123',
        ];
        $apl = [(object) [
            'aplp_ref_tipo' => 'PEP',
            'aplp_ref_sucursal' => 0,
            'aplp_ref_nro' => 55,
            'aplp_ref_letra' => 'X',
        ]];
        $comPorPep = [
            '000123|PEP|X|0|55' => [(object) [
                'aplp_tipo' => 'COM',
                'aplp_letra' => 'X',
                'aplp_sucursal' => 2,
                'aplp_nro' => 880,
                'aplp_proveedor' => '000123',
                'aplp_ref_tipo' => 'PEP',
                'aplp_ref_letra' => 'X',
                'aplp_ref_sucursal' => 0,
                'aplp_ref_nro' => 55,
            ]],
        ];
        $indice = [
            'por_com' => [
                '2|880' => [(object) ['id' => 901, 'empresa_id' => 1, 'proveedor_id' => 5, 'ordencompra_id' => null]],
            ],
            'por_com_prov' => [
                '5|2|880' => [(object) ['id' => 901, 'empresa_id' => 1, 'proveedor_id' => 5, 'ordencompra_id' => null]],
            ],
        ];

        $r = ComprasCpRecepcionAnitaBackfillSupport::resolverCp($cp, $apl, $comPorPep, $indice);

        $this->assertSame('ok', $r['stat']);
        $this->assertSame('pep', $r['via']);
        $this->assertSame(901, $r['recepciones'][0]['id']);
    }
}

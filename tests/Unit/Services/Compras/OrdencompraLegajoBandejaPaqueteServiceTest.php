<?php

namespace Tests\Unit\Services\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Services\Compras\OrdencompraLegajoBandejaPaqueteService;
use Tests\TestCase;

class OrdencompraLegajoBandejaPaqueteServiceTest extends TestCase
{
    public function test_precarga_pertenece_al_legajo_con_numero_equivalente(): void
    {
        $svc = app(OrdencompraLegajoBandejaPaqueteService::class);
        $oc = new Ordencompra(['empresa_id' => 4, 'numeroordencompra' => '223512']);
        $pre = new Precarga_Comprobante_Proveedor(['empresa_id' => 4, 'numeroordencompra' => 'OC 223512']);

        $this->assertTrue($svc->precargaPerteneceAlLegajo($oc, $pre));
    }

    public function test_precarga_de_otra_empresa_no_pertenece(): void
    {
        $svc = app(OrdencompraLegajoBandejaPaqueteService::class);
        $oc = new Ordencompra(['empresa_id' => 4, 'numeroordencompra' => '223512']);
        $pre = new Precarga_Comprobante_Proveedor(['empresa_id' => 1, 'numeroordencompra' => '223512']);

        $this->assertFalse($svc->precargaPerteneceAlLegajo($oc, $pre));
    }

    public function test_marca_facturas_ya_cargadas_en_cxp_por_numero(): void
    {
        $svc = app(OrdencompraLegajoBandejaPaqueteService::class);
        $facturas = [
            ['id' => 501, 'origen' => 'precarga', 'etiqueta' => 'FIS A 3093-00129773'],
            ['id' => 502, 'origen' => 'precarga', 'etiqueta' => 'NCC A 3093-00040425'],
            ['id' => 500, 'origen' => 'precarga', 'etiqueta' => 'FIS A 3093-00125541'],
            ['id' => 562, 'origen' => 'precarga', 'etiqueta' => 'CIS A 3093-00038947'],
        ];
        $comprobantes = [
            ['id' => 20795, 'precarga_id' => null, 'letra' => 'A', 'sucursal' => 3093, 'numerocomprobante' => 125541, 'etiqueta' => 'A 3093-00125541'],
            ['id' => 20780, 'precarga_id' => null, 'letra' => 'A', 'sucursal' => 3093, 'numerocomprobante' => 38947, 'etiqueta' => 'A 3093-00038947'],
        ];

        $out = $svc->marcarFacturasCargadasEnCxp($facturas, $comprobantes);

        $this->assertFalse($out[0]['cargado_cxp']);
        $this->assertFalse($out[1]['cargado_cxp']);
        $this->assertTrue($out[2]['cargado_cxp']);
        $this->assertTrue($out[3]['cargado_cxp']);
    }

    public function test_marca_factura_cargada_por_precarga_id(): void
    {
        $svc = app(OrdencompraLegajoBandejaPaqueteService::class);
        $out = $svc->marcarFacturasCargadasEnCxp(
            [['id' => 10, 'origen' => 'precarga', 'etiqueta' => 'FC A 0001-00000001']],
            [['id' => 99, 'precarga_id' => 10, 'letra' => 'B', 'sucursal' => 1, 'numerocomprobante' => 2, 'etiqueta' => 'B 0001-00000002']]
        );

        $this->assertTrue($out[0]['cargado_cxp']);
    }

    public function test_fusiona_comprobantes_sin_pdf_en_el_listado(): void
    {
        $svc = app(OrdencompraLegajoBandejaPaqueteService::class);
        $out = $svc->fusionarComprobantesEnFacturas(
            [
                [
                    'id' => 645,
                    'origen' => 'precarga',
                    'etiqueta' => 'ND A 0005-00004601',
                    'cargado_cxp' => true,
                    'url_pdf' => '/pdf/645',
                ],
            ],
            [
                [
                    'id' => 27647,
                    'precarga_id' => null,
                    'letra' => 'A',
                    'sucursal' => 5,
                    'numerocomprobante' => 4601,
                    'etiqueta' => 'ND A 0005-00004601',
                    'tipo' => 'ND',
                    'tipo_label' => 'ND',
                    'fecha' => '01/04/2026',
                    'origen_label' => 'Importado desde Anita',
                    'url' => '/cxp/27647',
                ],
                [
                    'id' => 27643,
                    'precarga_id' => null,
                    'letra' => 'A',
                    'sucursal' => 5,
                    'numerocomprobante' => 4419,
                    'etiqueta' => 'ND A 0005-00004419',
                    'tipo' => 'ND',
                    'tipo_label' => 'ND',
                    'fecha' => '15/03/2026',
                    'origen_label' => 'Importado desde Anita',
                    'url' => '/cxp/27643',
                ],
            ]
        );

        $this->assertCount(2, $out);
        $porEtiqueta = [];
        foreach ($out as $fila) {
            $porEtiqueta[(string) ($fila['etiqueta'] ?? '')] = $fila;
        }
        $this->assertSame('/pdf/645', $porEtiqueta['ND A 0005-00004601']['url_pdf'] ?? null);
        $this->assertSame('/cxp/27647', $porEtiqueta['ND A 0005-00004601']['url_comprobante'] ?? null);
        $this->assertNull($porEtiqueta['ND A 0005-00004419']['url_pdf'] ?? null);
        $this->assertSame('/cxp/27643', $porEtiqueta['ND A 0005-00004419']['url_comprobante'] ?? null);
    }

    public function test_referencia_cp_sintetica_no_es_asignable(): void
    {
        $svc = app(OrdencompraLegajoBandejaPaqueteService::class);

        $this->assertTrue($svc->esReferenciaAsignable(490));
        $this->assertTrue($svc->esReferenciaAsignable('490'));
        $this->assertTrue($svc->esReferenciaAsignable('anita-12345'));
        $this->assertFalse($svc->esReferenciaAsignable('cp-21671'));
        $this->assertFalse($svc->esReferenciaAsignable('cp-0'));
        $this->assertFalse($svc->esReferenciaAsignable('0'));
        $this->assertFalse($svc->esReferenciaAsignable(''));
        $this->assertFalse($svc->esReferenciaAsignable('abc'));
    }

    public function test_fusion_oc_anual_genera_ids_cp_que_no_son_asignables(): void
    {
        $svc = app(OrdencompraLegajoBandejaPaqueteService::class);
        $out = $svc->fusionarComprobantesEnFacturas(
            [
                [
                    'id' => 490,
                    'origen' => 'precarga',
                    'etiqueta' => 'FIS A 0070-00374589',
                    'cargado_cxp' => false,
                ],
            ],
            [
                [
                    'id' => 21671,
                    'precarga_id' => null,
                    'letra' => 'A',
                    'sucursal' => 70,
                    'numerocomprobante' => 354411,
                    'etiqueta' => 'FC A 0070-00354411',
                    'tipo' => 'FC',
                    'tipo_label' => 'FC',
                    'fecha' => '01/05/2026',
                    'origen_label' => 'Comprobante cargado en CxP',
                    'url' => '/cxp/21671',
                ],
            ]
        );

        $ids = array_map(static fn (array $f) => (string) $f['id'], $out);
        $this->assertContains('490', $ids);
        $this->assertContains('cp-21671', $ids);
        $this->assertTrue($svc->esReferenciaAsignable('490'));
        $this->assertFalse($svc->esReferenciaAsignable('cp-21671'));
    }

    public function test_adjunta_pagos_y_saldo_a_facturas_del_paquete(): void
    {
        $svc = app(OrdencompraLegajoBandejaPaqueteService::class);
        $facturas = [
            [
                'id' => 10,
                'origen' => 'precarga',
                'etiqueta' => 'FC A 0001-00000001',
                'comprobante_proveedor_id' => 99,
                'total' => 1000.0,
                'cargado_cxp' => true,
            ],
            [
                'id' => 'cp-88',
                'origen' => 'comprobante',
                'etiqueta' => 'FC B 0001-00000002',
                'total' => 500.0,
                'cargado_cxp' => true,
            ],
            [
                'id' => 11,
                'origen' => 'precarga',
                'etiqueta' => 'FC C 0001-00000003',
                'cargado_cxp' => false,
            ],
        ];
        $porCp = [
            99 => [[
                'id' => 7,
                'etiqueta' => 'OPP 1-100',
                'monto_aplicado' => 400.0,
                'estado' => 'CONFIRMADA',
            ]],
        ];

        $out = $svc->adjuntarPagosAFacturas($facturas, $porCp);

        $this->assertTrue($out[0]['tiene_pagos']);
        $this->assertSame(400.0, $out[0]['total_pagado']);
        $this->assertSame(600.0, $out[0]['saldo']);
        $this->assertCount(1, $out[0]['pagos']);
        $this->assertSame(88, $out[1]['comprobante_proveedor_id']);
        $this->assertFalse($out[1]['tiene_pagos']);
        $this->assertNull($out[1]['total_pagado']);
        $this->assertFalse($out[2]['tiene_pagos']);
    }

    public function test_fusion_guarda_comprobante_proveedor_id(): void
    {
        $svc = app(OrdencompraLegajoBandejaPaqueteService::class);
        $out = $svc->fusionarComprobantesEnFacturas(
            [
                [
                    'id' => 645,
                    'origen' => 'precarga',
                    'etiqueta' => 'ND A 0005-00004601',
                    'cargado_cxp' => true,
                    'url_pdf' => '/pdf/645',
                ],
            ],
            [
                [
                    'id' => 27647,
                    'precarga_id' => 645,
                    'letra' => 'A',
                    'sucursal' => 5,
                    'numerocomprobante' => 4601,
                    'etiqueta' => 'ND A 0005-00004601',
                    'tipo' => 'ND',
                    'tipo_label' => 'ND',
                    'fecha' => '01/04/2026',
                    'total' => 1234.5,
                    'origen_label' => 'Importado desde Anita',
                    'url' => '/cxp/27647',
                ],
            ]
        );

        $this->assertSame(27647, $out[0]['comprobante_proveedor_id']);
        $this->assertSame(1234.5, $out[0]['total']);
    }

    public function test_incorpora_com_del_cp_anual_aunque_la_precarga_tenga_otra(): void
    {
        $svc = app(OrdencompraLegajoBandejaPaqueteService::class);
        $out = $svc->incorporarAsignacionesDeComprobantesCxp(
            [
                404 => [65392],
                927 => [64439],
            ],
            [
                [
                    'id' => 26847,
                    'precarga_id' => null,
                    'letra' => 'A',
                    'sucursal' => 3,
                    'numerocomprobante' => 18,
                ],
            ],
            [
                26847 => [64439, 65392],
            ],
            [
                ['id' => 404, 'letra' => 'A', 'sucursal' => 3, 'numerocomprobante' => 18],
                ['id' => 927, 'letra' => 'A', 'sucursal' => 3, 'numerocomprobante' => 57],
            ],
        );

        $this->assertSame([65392, 64439], $out[404]);
        $this->assertSame([64439], $out[927]);
    }

    public function test_incorpora_com_de_cp_sin_precarga_con_clave_sintetica(): void
    {
        $svc = app(OrdencompraLegajoBandejaPaqueteService::class);
        $out = $svc->incorporarAsignacionesDeComprobantesCxp(
            [927 => [65392]],
            [
                [
                    'id' => 26847,
                    'precarga_id' => null,
                    'letra' => 'A',
                    'sucursal' => 3,
                    'numerocomprobante' => 18,
                ],
            ],
            [26847 => [64439]],
            [
                ['id' => 927, 'letra' => 'A', 'sucursal' => 3, 'numerocomprobante' => 57],
            ],
        );

        $this->assertSame([64439], $out['cp-26847']);
        $this->assertSame([65392], $out[927]);
    }
}

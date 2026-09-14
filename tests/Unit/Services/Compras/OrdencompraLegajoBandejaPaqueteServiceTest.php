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
}

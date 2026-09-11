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
}

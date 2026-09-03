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
}

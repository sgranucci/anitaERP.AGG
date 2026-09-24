<?php

namespace Tests\Unit\Support\Contable;

use App\Support\Contable\MayorFuenteConsultaSupport;
use App\Support\Contable\MayorPlanoCuentaListadoFiltros;
use Illuminate\Http\Request;
use Tests\TestCase;

class MayorPlanoCuentaListadoFiltrosTest extends TestCase
{
    public function test_fuente_erp_ignora_incluye_subdiario_desmarcado(): void
    {
        $filtros = MayorPlanoCuentaListadoFiltros::resolverDesdeRequest(
            Request::create('/contable/mayor-plano-cuenta', 'GET', [
                'fuente_mayor' => MayorFuenteConsultaSupport::MODO_ERP,
                'incluye_subdiario' => 0,
            ])
        );

        $this->assertSame(MayorFuenteConsultaSupport::MODO_ERP, $filtros['fuente_mayor']);
        $this->assertTrue($filtros['incluye_subdiario']);
        $this->assertTrue(MayorPlanoCuentaListadoFiltros::incluyeSubdiarioEfectivo($filtros));
        $this->assertArrayNotHasKey(
            'incluye_subdiario',
            MayorPlanoCuentaListadoFiltros::paraQueryString($filtros)
        );
    }

    public function test_fuente_anita_respeta_incluye_subdiario_desmarcado(): void
    {
        $filtros = MayorPlanoCuentaListadoFiltros::resolverDesdeRequest(
            Request::create('/contable/mayor-plano-cuenta', 'GET', [
                'fuente_mayor' => MayorFuenteConsultaSupport::MODO_ANITA,
                'incluye_subdiario' => 0,
            ])
        );

        $this->assertSame(MayorFuenteConsultaSupport::MODO_ANITA, $filtros['fuente_mayor']);
        $this->assertFalse($filtros['incluye_subdiario']);
        $this->assertFalse(MayorPlanoCuentaListadoFiltros::incluyeSubdiarioEfectivo($filtros));
        $this->assertSame(
            0,
            MayorPlanoCuentaListadoFiltros::paraQueryString($filtros)['incluye_subdiario']
        );
    }

    public function test_cuenta_hasta_vacio_no_iguala_a_desde(): void
    {
        $filtros = MayorPlanoCuentaListadoFiltros::resolverDesdeRequest(
            Request::create('/contable/mayor-plano-cuenta', 'GET', [
                'cuenta_desde' => '211010-001',
                'cuenta_hasta' => '',
            ])
        );

        $this->assertSame(211010001, $filtros['cuenta_desde']);
        $this->assertSame(0, $filtros['cuenta_hasta']);
        $this->assertTrue(MayorPlanoCuentaListadoFiltros::tieneSeleccionParticularCuentas($filtros));
    }
}

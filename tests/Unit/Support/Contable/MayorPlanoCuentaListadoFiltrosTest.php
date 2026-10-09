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

    public function test_modulo_en_totales_oculta_el_detalle(): void
    {
        $filtros = MayorPlanoCuentaListadoFiltros::resolverDesdeRequest(
            Request::create('/contable/mayor-plano-cuenta', 'GET', [
                'modulo_movimientos' => 'compras',
            ])
        );

        $this->assertSame('compras', $filtros['modulo_movimientos']);
        $this->assertSame(MayorPlanoCuentaListadoFiltros::PRESENTACION_TOTALES, $filtros['presentacion_modulo']);
        $this->assertTrue(MayorPlanoCuentaListadoFiltros::esConsultaTotalesModulo($filtros));
        $this->assertArrayNotHasKey('presentacion_modulo', MayorPlanoCuentaListadoFiltros::paraQueryString($filtros));
    }

    public function test_modulo_en_movimientos_lista_imputaciones(): void
    {
        $filtros = MayorPlanoCuentaListadoFiltros::resolverDesdeRequest(
            Request::create('/contable/mayor-plano-cuenta', 'GET', [
                'modulo_movimientos' => 'caja',
                'presentacion_modulo' => 'movimientos',
            ])
        );

        $this->assertSame(MayorPlanoCuentaListadoFiltros::PRESENTACION_MOVIMIENTOS, $filtros['presentacion_modulo']);
        $this->assertFalse(MayorPlanoCuentaListadoFiltros::esConsultaTotalesModulo($filtros));
        $this->assertSame(
            'movimientos',
            MayorPlanoCuentaListadoFiltros::paraQueryString($filtros)['presentacion_modulo']
        );
        $this->assertSame(
            MayorPlanoCuentaListadoFiltros::firma($filtros),
            MayorPlanoCuentaListadoFiltros::firma(array_merge($filtros, [
                'presentacion_modulo' => MayorPlanoCuentaListadoFiltros::PRESENTACION_TOTALES,
            ]))
        );
    }

    public function test_sin_modulo_la_presentacion_no_pasa_a_totales(): void
    {
        $filtros = MayorPlanoCuentaListadoFiltros::resolverDesdeRequest(
            Request::create('/contable/mayor-plano-cuenta', 'GET', [
                'presentacion_modulo' => 'movimientos',
            ])
        );

        $this->assertFalse(MayorPlanoCuentaListadoFiltros::esConsultaTotalesModulo($filtros));
        $this->assertArrayNotHasKey('presentacion_modulo', MayorPlanoCuentaListadoFiltros::paraQueryString($filtros));
    }
}

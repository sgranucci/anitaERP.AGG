<?php

namespace Tests\Unit\Support\Contable;

use App\Support\Contable\Efe\EfePosicionFinancieraSupport;
use App\Support\Contable\EfeMensualListadoFiltros;
use Illuminate\Http\Request;
use Tests\TestCase;

class EfeMensualListadoFiltrosRangoTest extends TestCase
{
    public function test_sin_hasta_asume_mismo_mes(): void
    {
        $filtros = EfeMensualListadoFiltros::resolverDesdeRequest(
            Request::create('/', 'GET', [
                'empresa_id' => 1,
                'mes_desde' => 3,
                'anio_desde' => 2025,
            ])
        );

        $this->assertSame(3, $filtros['mes']);
        $this->assertSame(2025, $filtros['anio']);
        $this->assertSame(3, $filtros['mes_hasta']);
        $this->assertSame(2025, $filtros['anio_hasta']);
        $this->assertFalse(EfeMensualListadoFiltros::esRango($filtros));
        $this->assertCount(1, EfeMensualListadoFiltros::periodosMensuales($filtros));
    }

    public function test_con_hasta_arma_rango_capado(): void
    {
        $filtros = EfeMensualListadoFiltros::resolverDesdeRequest(
            Request::create('/', 'GET', [
                'empresa_id' => 1,
                'mes_desde' => 1,
                'anio_desde' => 2025,
                'mes_hasta' => 12,
                'anio_hasta' => 2025,
            ])
        );

        $this->assertTrue(EfeMensualListadoFiltros::esRango($filtros));
        $periodos = EfeMensualListadoFiltros::periodosMensuales($filtros);
        $this->assertCount(12, $periodos);
        $this->assertSame(['mes' => 1, 'anio' => 2025], $periodos[0]);
        $this->assertSame(['mes' => 12, 'anio' => 2025], $periodos[11]);
    }

    public function test_legacy_mes_anio_sigue_funcionando(): void
    {
        $filtros = EfeMensualListadoFiltros::resolverDesdeRequest(
            Request::create('/', 'GET', [
                'empresa_id' => 2,
                'mes' => 8,
                'anio' => 2026,
            ])
        );

        $this->assertSame(8, $filtros['mes']);
        $this->assertSame(2026, $filtros['anio']);
        $this->assertFalse(EfeMensualListadoFiltros::esRango($filtros));
    }

    public function test_fuente_periodo_anita_vs_erp(): void
    {
        $this->assertSame('anita', EfePosicionFinancieraSupport::fuenteDelPeriodo(2025, 6));
        $this->assertSame('anita', EfePosicionFinancieraSupport::fuenteDelPeriodo(2026, 7));
        $this->assertSame('erp', EfePosicionFinancieraSupport::fuenteDelPeriodo(2026, 8));
        $this->assertSame('erp', EfePosicionFinancieraSupport::fuenteDelPeriodo(2026, 11));
    }

    public function test_query_string_omite_hasta_si_no_hay_rango(): void
    {
        $qs = EfeMensualListadoFiltros::paraQueryString([
            'empresa_id' => 1,
            'moneda_id' => 1,
            'mes' => 5,
            'anio' => 2025,
            'mes_hasta' => 5,
            'anio_hasta' => 2025,
        ]);

        $this->assertArrayNotHasKey('mes_hasta', $qs);
        $this->assertArrayNotHasKey('anio_hasta', $qs);
        $this->assertSame(5, $qs['mes_desde']);
        $this->assertSame(2025, $qs['anio_desde']);
    }
}

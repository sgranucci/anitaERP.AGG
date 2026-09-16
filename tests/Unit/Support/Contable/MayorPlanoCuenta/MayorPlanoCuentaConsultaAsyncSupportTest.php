<?php

namespace Tests\Unit\Support\Contable\MayorPlanoCuenta;

use App\Support\Contable\MayorFuenteConsultaSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaConsultaAsyncSupport;
use Tests\TestCase;

class MayorPlanoCuentaConsultaAsyncSupportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'contable.mayor_plano_cuenta.async_habilitado' => true,
            'contable.mayor_plano_cuenta.async_dias_minimos' => 32,
            'contable.mayor_plano_cuenta.async_cuentas_lista_minimas' => 20,
            'contable.mayor_plano_cuenta.async_cuentas_rango_span_minimo' => 50_000_000,
        ]);
    }

    /** @return array<string, mixed> */
    private function filtrosPeriodoLargoTodasCuentas(string $fuente): array
    {
        return [
            'modo_periodo' => 'rango',
            'fecha_desde' => '2026-01-01',
            'fecha_hasta' => '2026-08-31',
            'fuente_mayor' => $fuente,
            'cuentas' => [],
            'cuenta_desde' => 0,
            'cuenta_hasta' => 0,
        ];
    }

    public function test_fuente_erp_no_encola_aunque_periodo_largo_y_cuentas_amplias(): void
    {
        $this->assertFalse(
            MayorPlanoCuentaConsultaAsyncSupport::debeEncolar(
                $this->filtrosPeriodoLargoTodasCuentas(MayorFuenteConsultaSupport::MODO_ERP)
            )
        );
    }

    public function test_fuente_anita_si_encola_periodo_largo_y_cuentas_amplias(): void
    {
        $this->assertTrue(
            MayorPlanoCuentaConsultaAsyncSupport::debeEncolar(
                $this->filtrosPeriodoLargoTodasCuentas(MayorFuenteConsultaSupport::MODO_ANITA)
            )
        );
    }

    public function test_sin_fuente_asume_erp_y_no_encola(): void
    {
        $filtros = $this->filtrosPeriodoLargoTodasCuentas(MayorFuenteConsultaSupport::MODO_ERP);
        unset($filtros['fuente_mayor']);

        $this->assertFalse(MayorPlanoCuentaConsultaAsyncSupport::debeEncolar($filtros));
    }
}

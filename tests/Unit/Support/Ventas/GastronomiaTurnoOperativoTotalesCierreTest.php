<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\GastronomiaTurnoOperativoTotalesSupport;
use Tests\TestCase;

class GastronomiaTurnoOperativoTotalesCierreTest extends TestCase
{
    public function test_resolver_ajustes_sin_diferencia_no_imputa_sobrante(): void
    {
        $totales = [
            'conciliacion_ok' => true,
            'diferencia_cobranza' => 0.0,
            'total_invitaciones' => 10.0,
            'redondeo_invitaciones_sugerido' => 10.0,
        ];

        $ajustes = GastronomiaTurnoOperativoTotalesSupport::resolverAjustesCierreConSobranteFaltanteResidual($totales);

        $this->assertSame(10.0, $ajustes['redondeo_invitaciones']);
        $this->assertSame(0.0, $ajustes['sobrante_faltante']);
        $this->assertFalse($ajustes['sobrante_faltante_auto']);
    }

    public function test_resolver_ajustes_imputa_residual_en_sobrante_faltante(): void
    {
        $totales = [
            'conciliacion_ok' => false,
            'diferencia_cobranza' => 150.0,
            'total_invitaciones' => 0.0,
            'redondeo_invitaciones_sugerido' => 0.0,
        ];

        $ajustes = GastronomiaTurnoOperativoTotalesSupport::resolverAjustesCierreConSobranteFaltanteResidual($totales);

        $this->assertSame(150.0, $ajustes['sobrante_faltante']);
        $this->assertTrue($ajustes['sobrante_faltante_auto']);
        $this->assertTrue(GastronomiaTurnoOperativoTotalesSupport::cierreCuadraConAjustesManuales(
            $totales,
            $ajustes['redondeo_invitaciones'],
            $ajustes['redondeo_turno'],
            $ajustes['sobrante_faltante'],
        ));
    }

    public function test_resolver_ajustes_absorbe_diferencia_pequena_en_redondeo_invitaciones(): void
    {
        $totales = [
            'conciliacion_ok' => false,
            'diferencia_cobranza' => 0.01,
            'total_invitaciones' => 5.0,
            'redondeo_invitaciones_sugerido' => 5.01,
        ];

        $ajustes = GastronomiaTurnoOperativoTotalesSupport::resolverAjustesCierreConSobranteFaltanteResidual($totales);

        $this->assertSame(5.01, $ajustes['redondeo_invitaciones']);
        $this->assertSame(0.0, $ajustes['sobrante_faltante']);
        $this->assertFalse($ajustes['sobrante_faltante_auto']);
    }
}

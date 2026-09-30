<?php

namespace Tests\Unit\Support\Contable\MayorPlanoCuenta;

use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;
use Tests\TestCase;

class MayorPlanoCuentaInclusionAsientosTest extends TestCase
{
    public function test_sin_inflacion_excluye_cierre_y_reapertura_del_ajuste(): void
    {
        $this->assertFalse(MayorPlanoCuentaSupport::movimientoVisiblePorTipoAsiento('AJ', 'sin_inflacion'));
        $this->assertFalse(MayorPlanoCuentaSupport::movimientoVisiblePorTipoAsiento('CIJ', 'sin_inflacion'));
        $this->assertFalse(MayorPlanoCuentaSupport::movimientoVisiblePorTipoAsiento(
            'APJ',
            'sin_inflacion',
            'Asiento de apertura aj.inflaci',
        ));

        $this->assertTrue(MayorPlanoCuentaSupport::movimientoVisiblePorTipoAsiento('CIR', 'sin_inflacion'));
        $this->assertTrue(MayorPlanoCuentaSupport::movimientoVisiblePorTipoAsiento('APE', 'sin_inflacion'));
        $this->assertTrue(MayorPlanoCuentaSupport::movimientoVisiblePorTipoAsiento(
            'APJ',
            'sin_inflacion',
            'Gestional Personal Temporario',
        ));
    }

    public function test_sin_cierre_conserva_la_reapertura_de_inflacion_y_saca_el_cierre(): void
    {
        $this->assertFalse(MayorPlanoCuentaSupport::movimientoVisiblePorTipoAsiento('CIJ', 'sin_cierre'));
        $this->assertFalse(MayorPlanoCuentaSupport::movimientoVisiblePorTipoAsiento('CIR', 'sin_cierre'));
        $this->assertTrue(MayorPlanoCuentaSupport::movimientoVisiblePorTipoAsiento('AJ', 'sin_cierre'));
        $this->assertTrue(MayorPlanoCuentaSupport::movimientoVisiblePorTipoAsiento(
            'APJ',
            'sin_cierre',
            'Asiento de apertura aj.inflaci',
        ));
    }

    public function test_sin_cierre_ni_inflacion_saca_ajuste_cierre_y_reapertura(): void
    {
        $this->assertFalse(MayorPlanoCuentaSupport::movimientoVisiblePorTipoAsiento('AJ', 'sin_cierre_ni_inflacion'));
        $this->assertFalse(MayorPlanoCuentaSupport::movimientoVisiblePorTipoAsiento('CIJ', 'sin_cierre_ni_inflacion'));
        $this->assertFalse(MayorPlanoCuentaSupport::movimientoVisiblePorTipoAsiento('CIR', 'sin_cierre_ni_inflacion'));
        $this->assertFalse(MayorPlanoCuentaSupport::movimientoVisiblePorTipoAsiento(
            'APJ',
            'sin_cierre_ni_inflacion',
            'Asiento de apertura aj.inflaci',
        ));
        $this->assertTrue(MayorPlanoCuentaSupport::movimientoVisiblePorTipoAsiento(
            'APJ',
            'sin_cierre_ni_inflacion',
            'Gestional Personal Temporario',
        ));
        $this->assertTrue(MayorPlanoCuentaSupport::movimientoVisiblePorTipoAsiento('APE', 'sin_cierre_ni_inflacion'));
    }
}

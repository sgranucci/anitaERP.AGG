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

    public function test_la_apertura_se_omite_solo_cuando_su_cierre_esta_en_la_ventana(): void
    {
        $this->assertTrue(MayorPlanoCuentaSupport::aperturaOmitidaPorCierreEnVentana(
            'APE',
            20260101,
            'sin_cierre_ni_inflacion',
            20250101,
            20261031,
        ));
        $this->assertTrue(MayorPlanoCuentaSupport::aperturaOmitidaPorCierreEnVentana(
            'APE',
            20260101,
            'sin_cierre',
            20251201,
            20260131,
        ));

        $this->assertFalse(MayorPlanoCuentaSupport::aperturaOmitidaPorCierreEnVentana(
            'APE',
            20260101,
            'sin_cierre_ni_inflacion',
            20260101,
            20261031,
        ));
        $this->assertFalse(MayorPlanoCuentaSupport::aperturaOmitidaPorCierreEnVentana(
            'APE',
            20250101,
            'sin_cierre',
            20250101,
            20261031,
        ));
        $this->assertFalse(MayorPlanoCuentaSupport::aperturaOmitidaPorCierreEnVentana(
            'APE',
            20260101,
            'todos',
            20250101,
            20261031,
        ));
        $this->assertFalse(MayorPlanoCuentaSupport::aperturaOmitidaPorCierreEnVentana(
            'APE',
            20260101,
            'sin_inflacion',
            20250101,
            20261031,
        ));
    }
}

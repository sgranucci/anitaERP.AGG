<?php

namespace Tests\Unit\Support\Contable;

use App\Support\Contable\PeriodoContableCierreSupport;
use Tests\TestCase;

class PeriodoContableCierreSupportFechaCerradaTest extends TestCase
{
    public function test_empresa_invalida_no_esta_cerrada(): void
    {
        $this->assertFalse(PeriodoContableCierreSupport::fechaEnPeriodoCerrado(
            0,
            '2026-08-31',
            PeriodoContableCierreSupport::ALCANCE_RECEPCION_PROVEEDOR,
        ));
    }

    public function test_fecha_vacia_no_esta_cerrada(): void
    {
        $this->assertFalse(PeriodoContableCierreSupport::fechaEnPeriodoCerrado(
            1,
            '',
            PeriodoContableCierreSupport::ALCANCE_CONTABLE,
        ));
    }
}

<?php

namespace Tests\Unit\Support\Contable\MayorPlanoCuenta;

use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaEmisorEnricher;
use PHPUnit\Framework\TestCase;

class MayorPlanoCuentaTipoImpuestoIeTest extends TestCase
{
    public function test_ico_sin_otro_comprobante_muestra_el_tipo_del_movimiento(): void
    {
        $this->assertSame(
            'EGR',
            MayorPlanoCuentaEmisorEnricher::tipoVisibleImpuestoIngresoEgreso('ICO', 'EGR', ['ICO']),
        );
    }

    public function test_ico_con_un_comprobante_muestra_esa_sigla(): void
    {
        $this->assertSame(
            'FCB',
            MayorPlanoCuentaEmisorEnricher::tipoVisibleImpuestoIngresoEgreso('ICO', 'EGR', ['ICO', 'FCB']),
        );
    }

    public function test_ico_con_varios_comprobantes_queda_en_el_tipo_del_movimiento(): void
    {
        $this->assertSame(
            'EGR',
            MayorPlanoCuentaEmisorEnricher::tipoVisibleImpuestoIngresoEgreso('ICO', 'EGR', ['FAC', 'NCA']),
        );
    }

    public function test_ido_sin_movimiento_muestra_ingreso(): void
    {
        $this->assertSame(
            'ING',
            MayorPlanoCuentaEmisorEnricher::tipoVisibleImpuestoIngresoEgreso('IDO', '', []),
        );
    }

    public function test_otro_tipo_no_cambia(): void
    {
        $this->assertSame(
            'EGR',
            MayorPlanoCuentaEmisorEnricher::tipoVisibleImpuestoIngresoEgreso('EGR', 'OPP', ['FAC']),
        );
    }
}

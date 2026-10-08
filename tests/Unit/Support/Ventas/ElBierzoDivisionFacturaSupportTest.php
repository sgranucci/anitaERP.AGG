<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\ElBierzoDivisionFacturaSupport;
use Tests\TestCase;

class ElBierzoDivisionFacturaSupportTest extends TestCase
{
    public function test_divide_parte_50_y_deja_entero_lo_que_no_divide(): void
    {
        config()->set('app.empresa', 'EL BIERZO');

        $cliente = (object) [
            'coeficienteextra' => 1.05,
            'coeficientes' => (object) [
                'porcentajedivision' => 50,
                'tasa' => 0,
            ],
        ];

        $plan = ElBierzoDivisionFacturaSupport::plan($cliente, '3', '001');

        $this->assertNotNull($plan);
        $this->assertFalse($plan['reparto101']);
        $this->assertSame(50.0, $plan['porcentaje']);
        $this->assertSame(1.05, $plan['extra']);

        $bierzo = ElBierzoDivisionFacturaSupport::partirLinea(7.5, 1.1, 0.55, 100, 'DIVIDE', 50, 1.05, false);
        $villa = ElBierzoDivisionFacturaSupport::partirLinea(7.5, 1.1, 0.55, 100, 'DIVIDE', 50, 1.05, true);
        $entero = ElBierzoDivisionFacturaSupport::partirLinea(8, 2, 1, 100, 'NO DIVIDE', 50, 1.05, false);
        $fuera = ElBierzoDivisionFacturaSupport::partirLinea(8, 2, 1, 100, 'NO DIVIDE', 50, 1.05, true);

        $this->assertSame(3.8, $bierzo['cantidad']);
        $this->assertSame(100.0, $bierzo['precio']);
        $this->assertSame(3.8, $villa['cantidad']);
        $this->assertSame(105.0, $villa['precio']);
        $this->assertSame(8.0, $entero['cantidad']);
        $this->assertFalse($entero['omitir']);
        $this->assertTrue($fuera['omitir']);
    }

    public function test_sin_coeficiente_el_divide_no_parte_y_el_101_si(): void
    {
        config()->set('app.empresa', 'EL BIERZO');
        config()->set('facturacion.COEFICIENTE_EXTRA_REPARTO_101', 1.10);

        $sinCoef = (object) ['coeficienteextra' => 1.05, 'coeficientes' => null];

        $this->assertNull(ElBierzoDivisionFacturaSupport::plan($sinCoef, '3', '001'));
        $this->assertNull(ElBierzoDivisionFacturaSupport::plan($sinCoef, '2', '001'));

        $plan101 = ElBierzoDivisionFacturaSupport::plan($sinCoef, '4', '001');
        $this->assertNotNull($plan101);
        $this->assertTrue($plan101['reparto101']);
        $this->assertSame(100.0, $plan101['porcentaje']);
        $this->assertSame(1.1, $plan101['extra']);
    }

    public function test_fuera_de_el_bierzo_no_hay_plan(): void
    {
        config()->set('app.empresa', 'AGG');

        $cliente = (object) [
            'coeficienteextra' => 1.05,
            'coeficientes' => (object) ['porcentajedivision' => 50, 'tasa' => 0],
        ];

        $this->assertNull(ElBierzoDivisionFacturaSupport::plan($cliente, '3', '001'));
    }
}

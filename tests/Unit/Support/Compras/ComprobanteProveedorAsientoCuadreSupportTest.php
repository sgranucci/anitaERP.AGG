<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorAsientoCuadreSupport;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorAsientoCuadreSupportTest extends TestCase
{
    public function test_detecta_centavos_a_imputar(): void
    {
        $this->assertTrue(ComprobanteProveedorAsientoCuadreSupport::hayDiferenciaAImputar(0.03));
        $this->assertTrue(ComprobanteProveedorAsientoCuadreSupport::hayDiferenciaAImputar(-0.01));
        $this->assertFalse(ComprobanteProveedorAsientoCuadreSupport::hayDiferenciaAImputar(0.0));
        $this->assertFalse(ComprobanteProveedorAsientoCuadreSupport::hayDiferenciaAImputar(0.004));
    }

    public function test_absorbe_centavos_sin_tocar_provision(): void
    {
        $lineas = [
            ['cuentacontable_id' => 10, 'importe' => 17833.03],
            ['cuentacontable_id' => 20, 'importe' => 2635.13],
            ['cuentacontable_id' => 99, 'importe' => 110015.67],
        ];

        $ajustadas = ComprobanteProveedorAsientoCuadreSupport::absorberCentavosEnDebe($lineas, 0.03, 99);

        $this->assertSame(17833.03, $ajustadas[0]['importe']);
        $this->assertSame(2635.16, $ajustadas[1]['importe']);
        $this->assertSame(110015.67, $ajustadas[2]['importe']);
        $this->assertEqualsWithDelta(130483.86, array_sum(array_column($ajustadas, 'importe')), 0.001);
    }

    public function test_no_ajusta_fuera_de_tolerancia(): void
    {
        $lineas = [
            ['cuentacontable_id' => 10, 'importe' => 100.00],
        ];

        $iguales = ComprobanteProveedorAsientoCuadreSupport::absorberCentavosEnDebe($lineas, 0.10, 0);

        $this->assertSame(100.00, $iguales[0]['importe']);
    }
}

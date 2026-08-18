<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\CobranzaNumeracionTransaccion;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CobranzaNumeracionTransaccionTest extends TestCase
{
    public function test_siguiente_desde_maximo_nulo_o_cero(): void
    {
        $this->assertSame('1', CobranzaNumeracionTransaccion::siguienteDesdeMaximo(null));
        $this->assertSame('1', CobranzaNumeracionTransaccion::siguienteDesdeMaximo(0));
    }

    public function test_siguiente_desde_maximo_positivo(): void
    {
        $this->assertSame('68', CobranzaNumeracionTransaccion::siguienteDesdeMaximo(67));
    }

    public function test_numerotransaccion_desde_codigo_venta_fac(): void
    {
        $this->assertSame(
            'B-00008-00807543',
            CobranzaNumeracionTransaccion::numerotransaccionDesdeCodigoVenta('FAC B-00008-00807543'),
        );
    }

    public function test_numerotransaccion_desde_codigo_venta_ya_normalizado(): void
    {
        $this->assertSame(
            'B-00003-01270324',
            CobranzaNumeracionTransaccion::numerotransaccionDesdeCodigoVenta('B-00003-01270324'),
        );
    }

    public function test_numerotransaccion_desde_codigo_vacio_falla(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CobranzaNumeracionTransaccion::numerotransaccionDesdeCodigoVenta('');
    }

    public function test_detecta_violacion_unique_cobranza(): void
    {
        $e = new \Exception(
            "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1-2-67' for key 'cobranza.empresa_tipo_numero_unique'"
        );

        $this->assertTrue(CobranzaNumeracionTransaccion::esViolacionUnicidadNumeracion($e));
    }

    public function test_detecta_violacion_unique_cobranza_postgres(): void
    {
        $e = new \Exception(
            'ERROR: duplicate key value violates unique constraint "cobranza_empresa_tipo_numero_unique"'
        );

        $this->assertTrue(CobranzaNumeracionTransaccion::esViolacionUnicidadNumeracion($e));
    }
}

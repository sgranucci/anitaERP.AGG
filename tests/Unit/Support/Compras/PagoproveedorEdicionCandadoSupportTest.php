<?php

namespace Tests\Unit\Support\Compras;

use App\Models\Compras\Pagoproveedor;
use App\Support\Compras\PagoproveedorEdicionCandadoSupport;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PagoproveedorEdicionCandadoSupportTest extends TestCase
{
    private function pago(array $attrs): Pagoproveedor
    {
        $pago = new Pagoproveedor();
        foreach ($attrs as $key => $value) {
            $pago->{$key} = $value;
        }

        return $pago;
    }

    public function test_editable_si_no_es_revertido_ni_compensatorio(): void
    {
        $pago = $this->pago([
            'pagoproveedor_origen_id' => null,
            'pagoproveedor_revertido_por_id' => null,
        ]);

        $this->assertTrue(PagoproveedorEdicionCandadoSupport::esEditable($pago));
        PagoproveedorEdicionCandadoSupport::assertEditable($pago);
        $this->assertTrue(true);
    }

    public function test_rechaza_compensatorio_aop(): void
    {
        $pago = $this->pago([
            'pagoproveedor_origen_id' => 100,
            'pagoproveedor_revertido_por_id' => null,
        ]);

        $this->assertTrue(PagoproveedorEdicionCandadoSupport::esCompensatorio($pago));
        $this->assertFalse(PagoproveedorEdicionCandadoSupport::esEditable($pago));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('compensatoria AOP');
        PagoproveedorEdicionCandadoSupport::assertEditable($pago);
    }

    public function test_rechaza_op_revertida(): void
    {
        $pago = $this->pago([
            'pagoproveedor_origen_id' => null,
            'pagoproveedor_revertido_por_id' => 200,
        ]);

        $this->assertTrue(PagoproveedorEdicionCandadoSupport::estaRevertido($pago));
        $this->assertFalse(PagoproveedorEdicionCandadoSupport::esEditable($pago));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ya revertida');
        PagoproveedorEdicionCandadoSupport::assertEditable($pago);
    }
}

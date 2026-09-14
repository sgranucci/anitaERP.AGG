<?php

namespace Tests\Unit\Support\Caja;

use App\Models\Caja\Caja_Movimiento;
use App\Support\Caja\IngresoEgresoEdicionCandadoSupport;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class IngresoEgresoEdicionCandadoSupportTest extends TestCase
{
    private function movimiento(array $attrs): Caja_Movimiento
    {
        $mov = new Caja_Movimiento();
        foreach ($attrs as $key => $value) {
            $mov->{$key} = $value;
        }

        return $mov;
    }

    public function test_editable_si_no_es_revertido_ni_compensatorio(): void
    {
        $mov = $this->movimiento([
            'caja_movimiento_origen_id' => null,
            'caja_movimiento_revertido_por_id' => null,
        ]);

        $this->assertTrue(IngresoEgresoEdicionCandadoSupport::esEditable($mov));
        IngresoEgresoEdicionCandadoSupport::assertEditable($mov);
        $this->assertTrue(true);
    }

    public function test_rechaza_compensatorio(): void
    {
        $mov = $this->movimiento([
            'caja_movimiento_origen_id' => 108063,
            'caja_movimiento_revertido_por_id' => null,
        ]);

        $this->assertTrue(IngresoEgresoEdicionCandadoSupport::esCompensatorio($mov));
        $this->assertFalse(IngresoEgresoEdicionCandadoSupport::esEditable($mov));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('anulación (compensatorio)');
        IngresoEgresoEdicionCandadoSupport::assertEditable($mov);
    }

    public function test_rechaza_revertido(): void
    {
        $mov = $this->movimiento([
            'caja_movimiento_origen_id' => null,
            'caja_movimiento_revertido_por_id' => 108078,
        ]);

        $this->assertTrue(IngresoEgresoEdicionCandadoSupport::estaRevertido($mov));
        $this->assertFalse(IngresoEgresoEdicionCandadoSupport::esEditable($mov));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ya revertido');
        IngresoEgresoEdicionCandadoSupport::assertEditable($mov);
    }
}

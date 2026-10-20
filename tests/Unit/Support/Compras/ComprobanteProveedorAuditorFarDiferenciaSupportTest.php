<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorAuditorFarDiferenciaSupport;
use PHPUnit\Framework\TestCase;

final class ComprobanteProveedorAuditorFarDiferenciaSupportTest extends TestCase
{
    public function test_ok_cuando_far_coincide_con_esperado(): void
    {
        $eval = ComprobanteProveedorAuditorFarDiferenciaSupport::evaluar([
            ['origen' => 'far', 'monto_neto' => 1000.0],
            ['origen' => 'far_diferencia', 'monto_neto' => 50.0],
            ['origen' => 'proveedor', 'monto_neto' => -1050.0],
        ], 1000.0);

        $this->assertTrue($eval['ok']);
        $this->assertSame(50.0, $eval['far_diferencia_esperada']);
    }

    public function test_desvio_cuando_far_asiento_absorbe_diferencia(): void
    {
        $eval = ComprobanteProveedorAuditorFarDiferenciaSupport::evaluar([
            ['origen' => 'far', 'monto_neto' => 1000.0],
            ['origen' => 'far_diferencia', 'monto_neto' => 50.0],
        ], 1050.0);

        $this->assertFalse($eval['ok']);
        $this->assertSame(50.0, $eval['desvio_far']);
        $this->assertNotNull($eval['motivo']);
    }

    public function test_lineas_desde_payload_preview(): void
    {
        $lineas = ComprobanteProveedorAuditorFarDiferenciaSupport::lineasDesdePayloadPreview([
            'origenes' => ['far', 'proveedor'],
            'debes' => [100.0, 0.0],
            'haberes' => [0.0, 100.0],
        ]);

        $this->assertSame('far', $lineas[0]['origen']);
        $this->assertSame(100.0, $lineas[0]['monto_neto']);
        $this->assertSame(-100.0, $lineas[1]['monto_neto']);
    }
}

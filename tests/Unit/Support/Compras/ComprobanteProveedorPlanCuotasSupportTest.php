<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorPlanCuotasSupport;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ComprobanteProveedorPlanCuotasSupportTest extends TestCase
{
    public function test_unificar_suma_el_total_y_conserva_el_vencimiento_de_la_primera(): void
    {
        $plan = ComprobanteProveedorPlanCuotasSupport::planUnificado([
            ['numero_cuota' => 2, 'fechavencimiento' => '2026-03-20', 'monto' => 1532.11, 'formapago_id' => 3],
            ['numero_cuota' => 1, 'fechavencimiento' => '2026-02-20', 'monto' => 1534.60, 'formapago_id' => 1],
            ['numero_cuota' => 3, 'fechavencimiento' => '2026-04-20', 'monto' => 1532.15, 'formapago_id' => 1],
        ], 4598.86, 2, 1540);

        $this->assertCount(1, $plan);
        $this->assertSame(1, $plan[0]['numero_cuota']);
        $this->assertSame('2026-02-20', $plan[0]['fechavencimiento']);
        $this->assertSame(4598.86, $plan[0]['monto']);
        $this->assertSame(2, $plan[0]['moneda_id']);
        $this->assertSame(1540.0, $plan[0]['cotizacion']);
        $this->assertSame(1, $plan[0]['formapago_id']);
    }

    public function test_manual_rechaza_una_suma_distinta_del_total(): void
    {
        $this->expectException(RuntimeException::class);

        ComprobanteProveedorPlanCuotasSupport::planManual([
            ['fechavencimiento' => '2026-02-20', 'monto' => '1.000,00'],
        ], 19919.96, 2, 1540);
    }

    public function test_manual_acepta_importe_con_coma_decimal(): void
    {
        $plan = ComprobanteProveedorPlanCuotasSupport::planManual([
            ['fechavencimiento' => '2026-02-20', 'monto' => '10.000,00'],
            ['fechavencimiento' => '2026-03-20', 'monto' => '9919,96'],
        ], 19919.96, 2, 1540);

        $this->assertSame('2026-02-20', $plan[0]['fechavencimiento']);
        $this->assertSame(10000.0, $plan[0]['monto']);
        $this->assertSame(9919.96, $plan[1]['monto']);
        $this->assertSame(1540.0, $plan[0]['cotizacion']);
        $this->assertSame(1540.0, $plan[1]['cotizacion']);
    }
}

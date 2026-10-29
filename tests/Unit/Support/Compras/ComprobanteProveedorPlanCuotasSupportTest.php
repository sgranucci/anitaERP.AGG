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

    public function test_unificar_pendientes_conserva_la_cuota_aplicada(): void
    {
        $cuotas = [];
        for ($i = 1; $i <= 3; $i++) {
            $cuotas[] = [
                'id' => $i,
                'numero_cuota' => $i,
                'fechavencimiento' => sprintf('2026-%02d-10', 9 + $i),
                'monto' => $i === 3 ? 100.02 : 100.00,
                'aplicado' => $i === 1 ? 100.00 : 0,
                'formapago_id' => 1,
                'proveedor_cuentacorriente_id' => 10 + $i,
            ];
        }

        $plan = ComprobanteProveedorPlanCuotasSupport::planUnificadoConservandoAplicaciones($cuotas, 300.02, 2, 1540);

        $this->assertCount(1, $plan['conservadas']);
        $this->assertSame(1, $plan['conservadas'][0]['numero_cuota']);
        $this->assertSame(1, $plan['conservadas'][0]['id']);
        $this->assertSame(100.0, $plan['conservadas'][0]['monto']);
        $this->assertSame(11, $plan['conservadas'][0]['proveedor_cuentacorriente_id']);
        $this->assertSame([], $plan['numeros_actualizar']);
        $this->assertSame([2, 3], $plan['numeros_borrar']);
        $this->assertSame(2, $plan['nueva']['numero_cuota']);
        $this->assertSame('2026-11-10', $plan['nueva']['fechavencimiento']);
        $this->assertSame(200.02, $plan['nueva']['monto']);
        $this->assertSame(1540.0, $plan['nueva']['cotizacion']);
    }

    public function test_unificar_pendientes_achica_la_cuota_parcial_y_suma_el_resto(): void
    {
        $plan = ComprobanteProveedorPlanCuotasSupport::planUnificadoConservandoAplicaciones([
            ['id' => 1, 'numero_cuota' => 1, 'fechavencimiento' => '2026-10-10', 'monto' => 100, 'aplicado' => 40, 'formapago_id' => 1],
            ['id' => 2, 'numero_cuota' => 2, 'fechavencimiento' => '2026-11-10', 'monto' => 100, 'aplicado' => 0, 'formapago_id' => 3],
        ], 200, 1, 1);

        $this->assertSame(40.0, $plan['conservadas'][0]['monto']);
        $this->assertSame([1], $plan['numeros_actualizar']);
        $this->assertSame([2], $plan['numeros_borrar']);
        $this->assertSame(2, $plan['nueva']['numero_cuota']);
        $this->assertSame('2026-10-10', $plan['nueva']['fechavencimiento']);
        $this->assertSame(160.0, $plan['nueva']['monto']);
        $this->assertSame(1, $plan['nueva']['formapago_id']);
    }

    public function test_unificar_pendientes_rechaza_una_sola_cuota_abierta(): void
    {
        $this->expectException(RuntimeException::class);

        ComprobanteProveedorPlanCuotasSupport::planUnificadoConservandoAplicaciones([
            ['numero_cuota' => 1, 'fechavencimiento' => '2026-10-10', 'monto' => 100, 'aplicado' => 100],
            ['numero_cuota' => 2, 'fechavencimiento' => '2026-11-10', 'monto' => 50, 'aplicado' => 0],
        ], 150, 1, 1);
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

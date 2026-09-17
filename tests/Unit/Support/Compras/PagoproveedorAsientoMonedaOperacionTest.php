<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\PagoproveedorAnticipoAsientoSupport;
use App\Support\Compras\PagoproveedorAsientoArmadoSupport;
use Tests\TestCase;

class PagoproveedorAsientoMonedaOperacionTest extends TestCase
{
    public function test_convertir_me_a_pes_al_tc_pago(): void
    {
        $pes = PagoproveedorAsientoArmadoSupport::convertirImporteAMonedaPago(100.0, 2, 1, 1535.0);
        $this->assertSame(153500.0, $pes);
    }

    public function test_convertir_pes_a_me_al_tc_pago(): void
    {
        $dol = PagoproveedorAsientoArmadoSupport::convertirImporteAMonedaPago(153500.0, 1, 2, 1535.0);
        $this->assertSame(100.0, $dol);
    }

    public function test_anticipo_no_multiplica_pes_por_tc_informativa(): void
    {
        $asiento = [
            [
                'debe' => '',
                'haber' => 37762191.31,
                'moneda_id' => 1,
                'cotizacion' => 1535,
            ],
            [
                'debe' => '',
                'haber' => 796670.99,
                'moneda_id' => 1,
                'cotizacion' => 1535,
            ],
            [
                'debe' => 38558862.30,
                'haber' => '',
                'moneda_id' => 1,
                'cotizacion' => 1535,
            ],
        ];

        $residual = 0.0;
        $monedaPago = 1;
        $monedaLocal = 1;
        $m = new \ReflectionMethod(PagoproveedorAnticipoAsientoSupport::class, 'aMonedaPago');
        $m->setAccessible(true);
        foreach ($asiento as $linea) {
            $debe = (float) ($linea['debe'] ?: 0);
            $haber = (float) ($linea['haber'] ?: 0);
            $neto = $haber - $debe;
            $residual += $m->invoke(
                null,
                $neto,
                (int) $linea['moneda_id'],
                $monedaPago,
                (float) $linea['cotizacion'],
                $monedaLocal
            );
        }

        $this->assertEqualsWithDelta(0.0, round($residual, 4), 0.01);
    }

    public function test_anticipo_viejo_bug_hubiera_inventado_millones(): void
    {
        $banco = 37762191.31;
        $ret = 796670.99;
        $provDol = 25119.78;
        $cot = 1535.0;
        $residualViejo = round(($banco + $ret - $provDol) * $cot, 4);
        $this->assertGreaterThan(1_000_000, $residualViejo);
    }
}

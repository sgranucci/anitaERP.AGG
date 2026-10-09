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

    public function test_suma_de_cheques_queda_en_dos_decimales(): void
    {
        $haber = 959760 + 1117500 + 159398 + 682149.7 + 525204.64;

        $asiento = PagoproveedorAsientoArmadoSupport::cerrarImportesAlCentavo([
            [
                'debe' => '',
                'haber' => $haber,
                'moneda_id' => 1,
                'cotizacion' => 1,
            ],
            [
                'debe' => 3444012.34,
                'haber' => '',
                'moneda_id' => 1,
                'cotizacion' => 1,
            ],
        ]);

        $this->assertSame('3444012.34', number_format((float) $asiento[0]['haber'], 2, '.', ''));
        $this->assertSame('3444012.34', number_format((float) $asiento[1]['debe'], 2, '.', ''));
    }

    public function test_cierra_un_centavo_en_la_linea_mayor(): void
    {
        $asiento = PagoproveedorAsientoArmadoSupport::cerrarImportesAlCentavo([
            ['debe' => 10.00, 'haber' => ''],
            ['debe' => '', 'haber' => 100.00],
            ['debe' => '', 'haber' => 9.99],
            ['debe' => 100.00, 'haber' => ''],
        ]);

        $this->assertSame('100.01', number_format((float) $asiento[1]['haber'], 2, '.', ''));
        $this->assertSame('9.99', number_format((float) $asiento[2]['haber'], 2, '.', ''));
        $debe = (float) $asiento[0]['debe'] + (float) $asiento[3]['debe'];
        $haber = (float) $asiento[1]['haber'] + (float) $asiento[2]['haber'];
        $this->assertSame(number_format($debe, 2, '.', ''), number_format($haber, 2, '.', ''));
    }

    public function test_no_absorbe_una_diferencia_grande(): void
    {
        $asiento = PagoproveedorAsientoArmadoSupport::cerrarImportesAlCentavo([
            ['debe' => 100.00, 'haber' => ''],
            ['debe' => '', 'haber' => 90.00],
        ]);

        $this->assertSame('100.00', number_format((float) $asiento[0]['debe'], 2, '.', ''));
        $this->assertSame('90.00', number_format((float) $asiento[1]['haber'], 2, '.', ''));
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

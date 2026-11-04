<?php

namespace Tests\Unit\Support\Contable;

use App\Models\Contable\Cuentacontable;
use App\Models\Contable\ReporteContableCuenta;
use App\Models\Contable\ReporteContableRubro;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleConjuntoSupport;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleCuentaRangoSupport;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleEliminacionSupport;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleLayoutResolver;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleParticipacionSupport;
use App\Support\Contable\ReporteDefinible\ReporteDefiniblePartidaPlanReader;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleProcesador;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleSaldoReader;
use App\Support\Contable\SumasSaldos\SumasSaldosProcesador;
use Illuminate\Database\Eloquent\Collection;
use ReflectionMethod;
use Tests\TestCase;

class ReporteDefinibleDiferenciaMesAnteriorTest extends TestCase
{
    public function test_la_cuenta_resta_el_mes_anterior_aunque_esa_columna_no_se_muestre(): void
    {
        $codigo = 412010001;
        $septiembre = -3841386737.63;
        $octubre = -4034162334.72;
        $presupuesto = -5870598278.63;

        $columnas = [
            ['key' => '202610_act', 'label' => 'Octubre/26', 'tipo' => 'actual'],
            ['key' => '202610_plan', 'label' => 'Presupuesto', 'tipo' => 'plan'],
            [
                'key' => '202610_dif_pre',
                'label' => 'Dif.Act./Pre.',
                'tipo' => 'var',
                'meta' => ['base_key' => '202610_act', 'ref_key' => '202610_plan'],
            ],
            [
                'key' => '202610_dif_mes',
                'label' => 'Dif.Act./Mes ant.',
                'tipo' => 'var',
                'meta' => ['base_key' => '202610_act', 'ref_key' => '202610_mesant'],
            ],
            [
                'key' => '202610_pct_mes',
                'label' => '%Dif.',
                'tipo' => 'var_pct',
                'meta' => ['base_key' => '202610_act', 'ref_key' => '202610_mesant'],
            ],
        ];
        $detalle = [
            7 => [
                '202610_act' => [$codigo => $octubre],
                '202610_plan' => [$codigo => $presupuesto],
                '202610_mesant' => [$codigo => $septiembre],
            ],
        ];

        $filas = $this->emitirCuentas($columnas, $detalle);
        $saldos = $filas[0]['saldos'];

        $this->assertEqualsWithDelta(-192775597.09, $saldos['202610_dif_mes'], 0.02);
        $this->assertEqualsWithDelta(5.02, $saldos['202610_pct_mes'], 0.02);
        $this->assertEqualsWithDelta(1836435943.91, $saldos['202610_dif_pre'], 0.02);
    }

    public function test_sin_movimiento_la_diferencia_contra_el_mes_anterior_no_copia_el_presupuesto(): void
    {
        $codigo = 416010001;
        $columnas = [
            ['key' => '202610_act', 'label' => 'Octubre/26', 'tipo' => 'actual'],
            ['key' => '202610_plan', 'label' => 'Presupuesto', 'tipo' => 'plan'],
            [
                'key' => '202610_dif_mes',
                'label' => 'Dif.Act./Mes ant.',
                'tipo' => 'var',
                'meta' => ['base_key' => '202610_act', 'ref_key' => '202610_mesant'],
            ],
        ];
        $detalle = [
            4 => [
                '202610_plan' => [$codigo => -310161.31],
            ],
        ];

        $filas = $this->emitirCuentas($columnas, $detalle);

        $this->assertEquals(0.0, $filas[0]['saldos']['202610_dif_mes']);
    }

    /**
     * @param  list<array<string, mixed>>  $columnas
     * @param  array<int, array<string, array<int, float>>>  $detalle
     * @return list<array<string, mixed>>
     */
    private function emitirCuentas(array $columnas, array $detalle): array
    {
        $codigo = (int) array_key_first(reset($detalle)['202610_act'] ?? reset($detalle)['202610_plan']);
        $cuenta = new ReporteContableCuenta([
            'origen' => 'R',
            'codigo_cuenta' => $codigo,
        ]);
        $cuenta->setRelation('cuentacontable', new Cuentacontable(['nombre' => 'VENTAS']));
        $rubro = new ReporteContableRubro();
        $rubro->setRelation('cuentas', new Collection([$cuenta]));

        $procesador = new ReporteDefinibleProcesador(
            $this->createMock(SumasSaldosProcesador::class),
            $this->createMock(ReporteDefinibleSaldoReader::class),
            $this->createMock(ReporteDefiniblePartidaPlanReader::class),
            $this->createMock(ReporteDefinibleConjuntoSupport::class),
            new ReporteDefinibleLayoutResolver(),
            new ReporteDefinibleCuentaRangoSupport(),
            $this->createMock(ReporteDefinibleEliminacionSupport::class),
            $this->createMock(ReporteDefinibleParticipacionSupport::class),
        );

        $filas = [];
        $metodo = new ReflectionMethod(ReporteDefinibleProcesador::class, 'emitirCuentas');
        $metodo->setAccessible(true);
        $argumentos = [
            $rubro,
            (int) array_key_first($detalle),
            1,
            $columnas,
            $detalle,
            null,
            [],
            [],
            &$filas,
        ];
        $metodo->invokeArgs($procesador, $argumentos);

        return $filas;
    }
}

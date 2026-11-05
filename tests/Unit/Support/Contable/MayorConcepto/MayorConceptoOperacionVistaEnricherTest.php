<?php

namespace Tests\Unit\Support\Contable\MayorConcepto;

use App\Support\Contable\MayorConcepto\MayorConceptoOperacionVistaEnricher;
use PHPUnit\Framework\TestCase;

class MayorConceptoOperacionVistaEnricherTest extends TestCase
{
    public function test_completa_cheque_oc_y_capex_sin_tocar_importes(): void
    {
        $enricher = new MayorConceptoOperacionVistaEnricher;
        $fila = $this->detalle([
            'asiento_id' => 10,
            'cheque' => '',
            'nro_oc' => 0,
            'debe' => 800.55,
            'haber' => 0.0,
            'retenciones' => 12.5,
            'cuenta' => 521180011,
            'concepto_id' => 24,
        ]);

        $salida = $enricher->aplicar([$fila], [
            10 => [
                'cheque' => '78521649',
                'nro_oc' => '175985',
                'ordencompra_id' => 29357,
                'capex_codigo' => '2036',
                'capex_id' => 4,
            ],
        ]);

        $this->assertSame('78521649', $salida[0]['cheque']);
        $this->assertSame('175985', $salida[0]['nro_oc']);
        $this->assertSame(29357, $salida[0]['ordencompra_id']);
        $this->assertSame('2036', $salida[0]['capex_codigo']);
        $this->assertSame(4, $salida[0]['capex_id']);
        $this->assertSame(800.55, $salida[0]['debe']);
        $this->assertSame(0.0, $salida[0]['haber']);
        $this->assertSame(12.5, $salida[0]['retenciones']);
        $this->assertSame(521180011, $salida[0]['cuenta']);
        $this->assertSame(24, $salida[0]['concepto_id']);
    }

    public function test_no_pisa_cheque_ni_oc_que_ya_estan(): void
    {
        $enricher = new MayorConceptoOperacionVistaEnricher;
        $fila = $this->detalle([
            'asiento_id' => 10,
            'cheque' => '111',
            'nro_oc' => 222,
            'ordencompra_id' => 9,
            'capex_codigo' => '2011',
            'capex_id' => 3,
        ]);

        $salida = $enricher->aplicar([$fila], [
            10 => [
                'cheque' => '78521649',
                'nro_oc' => '175985',
                'ordencompra_id' => 29357,
                'capex_codigo' => '2036',
                'capex_id' => 4,
            ],
        ]);

        $this->assertSame('111', $salida[0]['cheque']);
        $this->assertSame(222, $salida[0]['nro_oc']);
        $this->assertSame(9, $salida[0]['ordencompra_id']);
        $this->assertSame('2011', $salida[0]['capex_codigo']);
        $this->assertSame(3, $salida[0]['capex_id']);
    }

    public function test_varias_oc_se_listan_sin_enlace_unico(): void
    {
        $enricher = new MayorConceptoOperacionVistaEnricher;
        $salida = $enricher->aplicar([
            $this->detalle(['asiento_operacion_id' => 7, 'asiento_id' => 99]),
        ], [
            7 => [
                'cheque' => '82020387, 82020388',
                'nro_oc' => '171031, 175985',
                'ordencompra_id' => 0,
                'capex_codigo' => '2011, 2036',
                'capex_id' => 0,
            ],
        ]);

        $this->assertSame('82020387, 82020388', $salida[0]['cheque']);
        $this->assertSame('171031, 175985', $salida[0]['nro_oc']);
        $this->assertSame(0, $salida[0]['ordencompra_id']);
        $this->assertSame('2011, 2036', $salida[0]['capex_codigo']);
        $this->assertArrayNotHasKey('asiento_operacion_id', $salida[0]);
    }

    public function test_el_total_no_recibe_datos_de_operacion(): void
    {
        $enricher = new MayorConceptoOperacionVistaEnricher;
        $salida = $enricher->aplicar([
            [
                'tipo_fila' => 'total_cuenta',
                'asiento_id' => 10,
                'debe' => 1000.0,
                'haber' => 1000.0,
            ],
        ], [
            10 => [
                'cheque' => '78521649',
                'nro_oc' => '175985',
                'ordencompra_id' => 29357,
                'capex_codigo' => '2036',
                'capex_id' => 4,
            ],
        ]);

        $this->assertArrayNotHasKey('cheque', $salida[0]);
        $this->assertArrayNotHasKey('nro_oc', $salida[0]);
        $this->assertSame(1000.0, $salida[0]['debe']);
        $this->assertSame(1000.0, $salida[0]['haber']);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function detalle(array $extra): array
    {
        return array_merge([
            'tipo_fila' => 'detalle',
            'asiento_id' => 0,
            'cheque' => '',
            'nro_oc' => 0,
            'debe' => 10.0,
            'haber' => 0.0,
            'retenciones' => 0.0,
            'cuenta' => 1,
            'concepto_id' => 1,
        ], $extra);
    }
}

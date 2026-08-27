<?php

namespace Tests\Unit\Support\Contable;

use App\Support\Contable\CierreRendicionBingoAsientoSupport;
use App\Support\Contable\CierreRendicionBingoConceptoTipos;
use App\Support\Contable\CierreRendicionBingoTotalesSupport;
use Tests\TestCase;

class CierreRendicionBingoTotalesSupportTest extends TestCase
{
    public function test_importe_porc_recaud_es_cinco_por_ciento_si_no_hay_premio_cargado(): void
    {
        $meta = [
            'concepto' => 3,
            'tipo_conc' => CierreRendicionBingoConceptoTipos::PORC_RECAUD,
            'porcentaje' => 5.0,
        ];

        $this->assertSame(
            192750.0,
            CierreRendicionBingoTotalesSupport::importePagoConcepto($meta, [], 3855000.0),
        );
    }

    public function test_importe_porc_recaud_respeta_real_importado(): void
    {
        $meta = [
            'concepto' => 3,
            'tipo_conc' => CierreRendicionBingoConceptoTipos::PORC_RECAUD,
            'porcentaje' => 5.0,
        ];

        $this->assertSame(
            100.0,
            CierreRendicionBingoTotalesSupport::importePagoConcepto(
                $meta,
                [3 => ['pagado' => 100.0, 'real' => 100.0]],
                3855000.0,
            ),
        );
    }

    public function test_completar_catalogo_calcula_premio_5_si_falta_en_rendicion(): void
    {
        $concbIndex = [
            3 => [
                'concepto' => 3,
                'desc' => 'Premio 5% recaudacion',
                'tipo_conc' => CierreRendicionBingoConceptoTipos::PORC_RECAUD,
                'porcentaje' => 5.0,
            ],
            1 => [
                'concepto' => 1,
                'desc' => 'Bingo 47%',
                'tipo_conc' => CierreRendicionBingoConceptoTipos::BINGO,
                'porcentaje' => 47.0,
            ],
        ];

        [$acum, $tot] = CierreRendicionBingoTotalesSupport::completarPorcRecaudCatalogo(
            $concbIndex,
            3855000.0,
            [1 => ['pagado' => 1811850.0, 'real' => 1811850.0]],
            0.0,
        );

        $this->assertSame(192750.0, $tot);
        $this->assertSame(192750.0, $acum[3]['real']);
        $this->assertSame(1811850.0, $acum[1]['real']);
    }

    public function test_completar_catalogo_no_pisa_real_ya_acumulado(): void
    {
        $concbIndex = [
            3 => [
                'concepto' => 3,
                'tipo_conc' => CierreRendicionBingoConceptoTipos::PORC_RECAUD,
                'porcentaje' => 5.0,
            ],
        ];

        [$acum, $tot] = CierreRendicionBingoTotalesSupport::completarPorcRecaudCatalogo(
            $concbIndex,
            3855000.0,
            [3 => ['pagado' => 50.0, 'real' => 50.0]],
            50.0,
        );

        $this->assertSame(50.0, $tot);
        $this->assertSame(50.0, $acum[3]['real']);
    }

    public function test_asiento_dev_pozo_incluye_porcentaje_recaudacion(): void
    {
        $asientos = CierreRendicionBingoAsientoSupport::armarAsientos(
            [
                'in_monto' => 0.0,
                'tot_sobrante' => 0.0,
                'tot_redondeo' => 0.0,
                'tot_premio' => 231300.0,
                'tot_bingo' => 1811850.0,
                'tot_pozo' => 0.0,
                'tot_pantalla' => 0.0,
                'otros_premios' => 0.0,
                'dif_caja_asiento' => 0.0,
                'tot_porc_recaud' => 192750.0,
                'canones' => [],
                'tot_pago_hospital' => 0.0,
            ],
            [
                'cuenta_efectivo_id' => 1,
                'cuenta_premio53_id' => 2,
                'cuenta_pozo_bingo_id' => 3,
                'cuenta_pantalla_id' => 4,
                'cuenta_otros_premios_id' => 5,
                'cuenta_diferencia_caja_id' => 6,
                'cuenta_ventas_id' => 7,
                'cuenta_pozo58_id' => 8,
                'cuenta_pago_hospital_id' => 9,
                'cuenta_cont_hospital_id' => 10,
            ],
        );

        $devPozo = collect($asientos)->firstWhere('leyenda', 'Dev. pozo acum.');
        $this->assertIsArray($devPozo);

        $lineas = collect($devPozo['lineas']);
        $this->assertSame(2235900.0, (float) $lineas->firstWhere('concepto', 'Dev. pozo acum. — Pozo 58%')['debe']);
        $this->assertSame(2043150.0, (float) $lineas->firstWhere('concepto', 'Dev. pozo acum. — Premio 53%')['haber']);
        $this->assertSame(192750.0, (float) $lineas->firstWhere('concepto', 'Dev. pozo acum. — % recaudación')['haber']);
    }
}

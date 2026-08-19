<?php

namespace Tests\Unit\Support\Caja;

use App\Models\Caja\Bingo\BingoCarton;
use App\Models\Caja\Bingo\BingoConceptoRendicion;
use App\Models\Configuracion\Empresa;
use App\Support\Caja\AnitaSync\RendicionBingoAnitaImportMapper;
use Tests\TestCase;

class RendicionBingoAnitaImportMapperTest extends TestCase
{
    public function test_mapea_cartones_con_cantidad_y_omite_ceros(): void
    {
        $cartones = [
            new BingoCarton(['id' => 12, 'codigo_anita' => 2, 'codigo' => 'C1500', 'nombre' => 'Cartón $1500', 'precio_unitario' => 1500]),
            new BingoCarton(['id' => 5, 'codigo_anita' => 5, 'codigo' => 'C3000', 'nombre' => 'Cartón $3.000', 'precio_unitario' => 3000]),
        ];
        $cartones[0]->id = 12;
        $cartones[1]->id = 5;

        $filas = [
            (object) ['rendc_carton' => 2, 'rendc_cantidad' => 1170, 'rendc_valor' => 1500],
            (object) ['rendc_carton' => 5, 'rendc_cantidad' => 0, 'rendc_valor' => 3000],
            (object) ['rendc_carton' => 99, 'rendc_cantidad' => 10, 'rendc_valor' => 1000],
        ];

        $lineas = RendicionBingoAnitaImportMapper::lineasCarton($filas, $cartones);

        $this->assertCount(1, $lineas);
        $this->assertSame(12, $lineas[0]['carton_id']);
        $this->assertSame(1170, $lineas[0]['cantidad']);
        $this->assertEquals(1500.0, $lineas[0]['precio_unitario']);
    }

    public function test_montos_manuales_desde_premio_y_cabecera(): void
    {
        $bub = new BingoConceptoRendicion([
            'codigo' => 'BUB_APE',
            'codigo_anita' => 10,
            'base_calculo' => BingoConceptoRendicion::BASE_MANUAL,
            'es_saldo_rendicion' => false,
        ]);
        $bub->id = 21;
        $vales = new BingoConceptoRendicion([
            'codigo' => 'VALES',
            'codigo_anita' => null,
            'base_calculo' => BingoConceptoRendicion::BASE_MANUAL,
            'es_saldo_rendicion' => false,
        ]);
        $vales->id = 28;
        $bingo = new BingoConceptoRendicion([
            'codigo' => 'BINGO47',
            'codigo_anita' => 1,
            'base_calculo' => BingoConceptoRendicion::BASE_TOTAL_CARTONES,
            'es_saldo_rendicion' => false,
        ]);
        $bingo->id = 17;

        $montos = RendicionBingoAnitaImportMapper::montosManuales(
            [
                (object) ['rendp_concepto' => 10, 'rendp_real' => 21712.11, 'rendp_pagado' => 0],
                (object) ['rendp_concepto' => 1, 'rendp_real' => 1811850, 'rendp_pagado' => 0],
            ],
            [$bub, $vales, $bingo],
            (object) ['rendb_vales' => 100, 'rendb_refuer_prest' => 0],
        );

        $this->assertEquals(21712.11, $montos[21]);
        $this->assertEquals(100.0, $montos[28]);
        $this->assertArrayNotHasKey(17, $montos);
    }

    public function test_ajuste_deposito_va_a_sobrante_si_falta(): void
    {
        $sobrante = new BingoConceptoRendicion(['codigo' => 'SOBRANTE', 'es_saldo_rendicion' => false]);
        $sobrante->id = 29;
        $redondeo = new BingoConceptoRendicion(['codigo' => 'REDONDEO', 'es_saldo_rendicion' => false]);
        $redondeo->id = 30;

        $montos = RendicionBingoAnitaImportMapper::aplicarAjusteDeposito(
            [],
            [$sobrante, $redondeo],
            1546915.26,
            1546920.0,
        );

        $this->assertEquals(4.74, $montos[29]);
        $this->assertEquals(0.0, $montos[30]);
    }

    public function test_alias_premio_65_y_empresa_por_codigo(): void
    {
        $this->assertSame(55, RendicionBingoAnitaImportMapper::normalizarCodigoAnita(60));
        $this->assertSame(10, RendicionBingoAnitaImportMapper::normalizarCodigoAnita(10));

        $empresa = new Empresa(['codigo' => '2', 'nombre' => 'KANDIKO']);
        $empresa->id = 2;
        $this->assertSame(2, RendicionBingoAnitaImportMapper::empresaIdDesdeCodigoAnita(2, [$empresa]));
        $this->assertSame('2026-08-01', RendicionBingoAnitaImportMapper::fechaJornadaDesdeEntera(20260801));
    }
}

<?php

namespace Tests\Unit\Support\Contable;

use App\Models\Caja\Flash\FlashCaja;
use App\Models\Configuracion\Empresa;
use App\Support\Contable\FlashContableListadoFiltros;
use App\Support\Contable\FlashContableReporteSupport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Tests\TestCase;

class FlashContableReporteSupportTest extends TestCase
{
    public function test_metricas_mapean_el_flash_principal(): void
    {
        $flash = new FlashCaja([
            'cant_slots' => 700,
            'cant_rul' => 71,
            'win_ol_slot' => 100.5,
            'win_ol_rul' => 20.25,
            'slot_r' => 80.0,
            'rul_r' => 15.5,
            'bingo_cant_carton' => 2346,
            'bingo_total_venta' => 1000,
            'bingo_resultado' => 400.4,
            'ayb' => 250.1,
            'estac' => 33.3,
            'vending' => 44.4,
            'validado' => true,
        ]);

        $m = FlashContableReporteSupport::metricasDesdeFlash($flash);

        $this->assertSame(700, $m['q_pos_slots']);
        $this->assertEquals(100.5, $m['win_slots']);
        $this->assertSame(71, $m['q_pos_ruletas']);
        $this->assertEquals(20.25, $m['win_ruletas']);
        $this->assertSame(771, $m['q_posiciones']);
        $this->assertEquals(120.75, $m['win_electronico']);
        $this->assertEquals(95.5, $m['win_financiero']);
        $this->assertSame(2346, $m['cartones_bingo']);
        $this->assertEquals(1000.0, $m['ventas_bingo']);
        $this->assertEquals(400.4, $m['net_win_bingo']);
        $this->assertEquals(250.1, $m['ventas_ayb']);
        $this->assertEquals(33.3, $m['ventas_parking']);
        $this->assertEquals(44.4, $m['ventas_vending']);
        $this->assertTrue($m['flash_cerrado']);
    }

    public function test_armar_desde_flashes_pone_empresas_en_columnas(): void
    {
        $biyemas = $this->empresaStub(1, 'Biyemas');
        $kandiko = $this->empresaStub(2, 'Kandiko');

        $f1 = $this->flashStub(1, '2026-07-01', 770, 100, $biyemas, validado: true);
        $f2 = $this->flashStub(2, '2026-07-01', 300, 50, $kandiko);
        $f3 = $this->flashStub(1, '2026-07-02', 772, 80, $biyemas);

        $reporte = FlashContableReporteSupport::armarDesdeFlashes(
            new Collection([$f1, $f2, $f3]),
            [1, 2],
            [1 => 'Biyemas', 2 => 'Kandiko'],
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-03'),
        );

        $this->assertSame('Julio 2026', $reporte['periodo']);
        $this->assertCount(2, $reporte['empresas']);
        $this->assertCount(3, $reporte['filas']);
        $this->assertSame(2, $reporte['cantidad_dias']);
        $this->assertSame(29, $reporte['cantidad_columnas']);

        $dia1 = $reporte['filas'][0];
        $this->assertSame('01/07/2026', $dia1['fecha']);
        $this->assertSame(770, $dia1['empresas'][1]['q_pos_slots']);
        $this->assertSame(770, $dia1['empresas'][1]['q_posiciones']);
        $this->assertEquals(100.0, $dia1['empresas'][1]['win_slots']);
        $this->assertEquals(100.0, $dia1['empresas'][1]['win_electronico']);
        $this->assertTrue($dia1['empresas'][1]['flash_cerrado']);
        $this->assertSame(300, $dia1['empresas'][2]['q_posiciones']);
        $this->assertFalse($dia1['empresas'][2]['flash_cerrado']);

        $dia3 = $reporte['filas'][2];
        $this->assertFalse($dia3['tiene_dato']);
        $this->assertSame(0, $dia3['empresas'][1]['q_posiciones']);

        $this->assertSame(771, $reporte['totales'][1]['q_posiciones']);
        $this->assertEquals(180.0, $reporte['totales'][1]['win_electronico']);
        $this->assertEquals(50.0, $reporte['totales'][2]['win_electronico']);
        $this->assertSame(1, $reporte['totales'][1]['flash_cerrado']);
        $this->assertSame('1/2', $reporte['totales'][1]['flash_cerrado_texto']);
        $this->assertSame('0/1', $reporte['totales'][2]['flash_cerrado_texto']);
    }

    public function test_filtros_resuelven_empresas_y_mes(): void
    {
        $request = Request::create('/contable/flash-contable', 'GET', [
            'empresa_ids' => ['1', '2', '2'],
            'mes' => 8,
            'anio' => 2026,
        ]);

        $filtros = FlashContableListadoFiltros::resolverDesdeRequest($request);

        $this->assertSame([1, 2], $filtros['empresa_ids']);
        $this->assertSame(8, $filtros['mes']);
        $this->assertSame(2026, $filtros['anio']);
        $this->assertTrue(FlashContableListadoFiltros::tieneCriteriosAplicados($filtros));
        $this->assertSame(
            'Empresas: Biyemas, Kandiko — Mes: Agosto 2026',
            FlashContableListadoFiltros::subtitulo($filtros, 'Biyemas, Kandiko'),
        );
    }

    private function empresaStub(int $id, string $nombre): Empresa
    {
        $empresa = new Empresa(['nombre' => $nombre]);
        $empresa->id = $id;

        return $empresa;
    }

    private function flashStub(
        int $empresaId,
        string $fecha,
        int $slots,
        float $winOl,
        Empresa $empresa,
        bool $validado = false,
    ): FlashCaja {
        $flash = new FlashCaja([
            'empresa_id' => $empresaId,
            'fecha' => $fecha,
            'cant_slots' => $slots,
            'cant_rul' => 0,
            'win_ol_slot' => $winOl,
            'win_ol_rul' => 0,
            'slot_r' => 0,
            'rul_r' => 0,
            'bingo_cant_carton' => 0,
            'bingo_total_venta' => 0,
            'bingo_resultado' => 0,
            'ayb' => 0,
            'estac' => 0,
            'vending' => 0,
            'validado' => $validado,
        ]);
        $flash->setRelation('empresa', $empresa);

        return $flash;
    }
}

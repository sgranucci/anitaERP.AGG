<?php

namespace Tests\Unit\Support\Ventas\Waitry;

use App\Models\Ventas\TotemWaitryGastronomia;
use App\Support\Ventas\Waitry\WaitryInformeZConciliacionSupport;
use App\Support\Ventas\Waitry\WaitryInformeZVentaRealSupport;
use App\Support\Ventas\Waitry\WaitryTotemJornadaResumenSupport;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class WaitryInformeZVentaRealSupportTest extends TestCase
{
    public function test_no_pisa_a_cero_si_waitry_tiene_venta(): void
    {
        $r = WaitryInformeZVentaRealSupport::decidirRegeneracion(0.0, 658300.0, 3860003.80, 0.0);

        $this->assertSame('omitido_recomputo_cero', $r['decision']);
        $this->assertSame(658300.0, $r['venta_waitry']);
        $this->assertSame(3860003.80, $r['venta_erp']);
    }

    public function test_no_pisa_a_cero_si_solo_erp_tiene_venta(): void
    {
        $r = WaitryInformeZVentaRealSupport::decidirRegeneracion(0.0, 0.0, 1500.0, 0.0);

        $this->assertSame('omitido_recomputo_cero', $r['decision']);
    }

    public function test_no_pisa_a_cero_si_z_actual_tiene_monto(): void
    {
        $r = WaitryInformeZVentaRealSupport::decidirRegeneracion(0.0, 0.0, 0.0, 658300.0);

        $this->assertSame('omitido_recomputo_cero', $r['decision']);
    }

    public function test_regenera_si_recomputo_coincide_con_waitry_y_z_difiere(): void
    {
        $r = WaitryInformeZVentaRealSupport::decidirRegeneracion(658300.0, 658300.0, 3860003.80, 0.0);

        $this->assertSame('regenerar', $r['decision']);
    }

    public function test_ok_si_recomputo_waitry_y_z_ya_coinciden(): void
    {
        $r = WaitryInformeZVentaRealSupport::decidirRegeneracion(658300.0, 658300.0, 3860003.80, 658300.0);

        $this->assertSame('ok', $r['decision']);
    }

    public function test_revisar_si_recomputo_no_coincide_con_waitry(): void
    {
        $r = WaitryInformeZVentaRealSupport::decidirRegeneracion(100.0, 658300.0, 3860003.80, 658300.0);

        $this->assertSame('revisar_venta', $r['decision']);
    }

    public function test_regenera_si_recomputo_coincide_con_mp_contabilizado_aunque_waitry_este_viejo(): void
    {
        $r = WaitryInformeZVentaRealSupport::decidirRegeneracion(
            2665500.0,
            2660100.0,
            7065605.4,
            2660100.0,
            0.02,
            2665500.0,
        );

        $this->assertSame('regenerar', $r['decision']);
        $this->assertSame(2665500.0, $r['mp_contabilizado']);
    }

    public function test_ok_si_recomputo_ya_igual_a_z_y_asientos(): void
    {
        $r = WaitryInformeZVentaRealSupport::decidirRegeneracion(
            482700.0,
            479000.0,
            2599520.94,
            482700.0,
            0.02,
            482700.0,
        );

        $this->assertSame('ok', $r['decision']);
    }

    public function test_ok_si_todo_es_cero(): void
    {
        $r = WaitryInformeZVentaRealSupport::decidirRegeneracion(0.0, 0.0, 0.0, 0.0);

        $this->assertSame('ok', $r['decision']);
    }

    public function test_resumen_z_vacio_no_es_confiable_si_waitry_tiene_venta(): void
    {
        $detalle = [
            'resumen_informe_z' => [
                'por_totem' => [],
                'total_general' => ['total_ingreso' => 0, 'por_medio_pago' => [], 'cantidad_ordenes' => 0],
            ],
            'resumen_totems' => [
                'por_totem' => [[
                    'totem_id' => 2,
                    'por_medio_pago' => [
                        ['tipo' => 'credit_card', 'total' => 240700, 'cantidad' => 21],
                        ['tipo' => 'totalcoin', 'total' => 95200, 'cantidad' => 6],
                    ],
                    'total_ingreso' => 335900,
                    'cantidad_ordenes' => 27,
                ]],
                'total_general' => [
                    'total_ingreso' => 335900,
                    'por_medio_pago' => [
                        ['tipo' => 'credit_card', 'total' => 240700, 'cantidad' => 21],
                        ['tipo' => 'totalcoin', 'total' => 95200, 'cantidad' => 6],
                    ],
                    'cantidad_ordenes' => 27,
                ],
            ],
        ];

        $this->assertFalse(WaitryInformeZConciliacionSupport::resumenInformeZPersistidoEsConfiable(
            $detalle['resumen_informe_z'],
            $detalle,
        ));
        $this->assertSame(335900.0, WaitryInformeZConciliacionSupport::totalInformeZDesdeResumenTotems($detalle));

        $resumen = WaitryInformeZConciliacionSupport::resumenSistemaDesdeDetalleCierre($detalle, 1);
        $this->assertGreaterThan(0.0, $resumen['total_general']['total_ingreso']);
    }

    public function test_nota_credito_total_saca_la_orden_waitry_del_informe_z(): void
    {
        config(['waitry.tipo_pago_cuentacaja' => ['totalcoin' => 201]]);

        $lineas = WaitryInformeZVentaRealSupport::ajustarLineasInformeZPorNotaCredito(
            [[
                'venta_id' => 258791,
                'waitry_order_id' => 19204131,
                'facturada_erp' => true,
                'waitry_cobro_totem' => true,
                'waitry_tipo_pago' => 'totalcoin',
                'paid_waitry' => true,
                'total' => 33800.0,
                'monto_cobro_waitry' => 33800.0,
                'waitry_table_id' => 99,
            ]],
            [258791 => 33800.0],
            [258791 => -33800.0],
        );

        $this->assertTrue($lineas[0]['anulada_por_nota_credito']);
        $this->assertSame(0.0, $lineas[0]['monto_cobro_waitry']);
        $this->assertFalse(WaitryTotemJornadaResumenSupport::lineaEntraInformeZSistema($lineas[0]));

        $totem = new TotemWaitryGastronomia;
        $totem->id = 4;
        $totem->empresa_id = 3;
        $totem->waitry_table_id = 99;

        $resumen = WaitryTotemJornadaResumenSupport::armarParaInformeZ(new Collection([$totem]), $lineas, 3);
        $this->assertSame(0.0, $resumen['total_general']['total_ingreso']);
    }

    public function test_nota_credito_parcial_deja_el_neto_en_el_informe_z(): void
    {
        $lineas = WaitryInformeZVentaRealSupport::ajustarLineasInformeZPorNotaCredito(
            [[
                'venta_id' => 10,
                'empresa_id' => 3,
                'facturada_erp' => true,
                'waitry_cobro_totem' => true,
                'waitry_tipo_pago' => 'totalcoin',
                'paid_waitry' => true,
                'total' => 27800.0,
                'monto_cobro_waitry' => 27800.0,
            ]],
            [10 => 27800.0],
            [10 => -5100.0],
        );

        $this->assertSame(22700.0, $lineas[0]['monto_cobro_waitry']);
        $this->assertArrayNotHasKey('anulada_por_nota_credito', $lineas[0]);
        $this->assertTrue(WaitryTotemJornadaResumenSupport::lineaEntraInformeZSistema($lineas[0]));
    }
}

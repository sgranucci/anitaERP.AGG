<?php

namespace Tests\Unit\Support\Ventas\Waitry;

use App\Support\Ventas\Waitry\WaitryInformeZConciliacionSupport;
use App\Support\Ventas\Waitry\WaitryInformeZVentaRealSupport;
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
}

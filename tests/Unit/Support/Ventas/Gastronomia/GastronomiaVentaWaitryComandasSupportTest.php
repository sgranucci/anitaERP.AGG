<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Models\Ventas\VentaGastronomiaEmision;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoMedioSupport;
use App\Support\Ventas\Gastronomia\GastronomiaVentaWaitryComandasSupport;
use Tests\TestCase;

final class GastronomiaVentaWaitryComandasSupportTest extends TestCase
{
    public function test_comandas_desde_json_normaliza_filas(): void
    {
        $emision = new VentaGastronomiaEmision([
            'identificador_pc' => GastronomiaVentaWaitryComandasSupport::IDENTIFICADOR_PC_CIERRE_JORNADA,
            'cierre_jornada_proceso_lote' => 2,
            'waitry_comandas_json' => [
                [
                    'waitry_order_id' => 200,
                    'display_id' => 'M-2',
                    'total' => 1500.,
                    'medio_waitry_clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR,
                    'placed_at' => '2026-06-03 15:30:00',
                ],
                [
                    'waitry_order_id' => 100,
                    'referencia_waitry' => 'barra',
                    'total' => 500.,
                ],
            ],
        ]);

        $comandas = GastronomiaVentaWaitryComandasSupport::comandasDesdeEmision($emision);

        $this->assertCount(2, $comandas);
        $this->assertSame(100, $comandas[0]['waitry_order_id']);
        $this->assertSame('barra', $comandas[0]['referencia_waitry']);
        $this->assertSame(200, $comandas[1]['waitry_order_id']);
        $this->assertSame('QR', $comandas[1]['medio_waitry_label']);
        $this->assertSame(2000., GastronomiaVentaWaitryComandasSupport::totalComandas($emision));
        $this->assertTrue(GastronomiaVentaWaitryComandasSupport::esFacturaCierreJornadaProceso($emision));
    }

    public function test_fallback_comanda_unica_desde_waitry_order_id(): void
    {
        $emision = new VentaGastronomiaEmision(['waitry_order_id' => 55]);
        $comandas = GastronomiaVentaWaitryComandasSupport::comandasDesdeEmision($emision);

        $this->assertCount(1, $comandas);
        $this->assertSame(55, $comandas[0]['waitry_order_id']);
        $this->assertFalse(GastronomiaVentaWaitryComandasSupport::esFacturaCierreJornadaProceso($emision));
    }
}

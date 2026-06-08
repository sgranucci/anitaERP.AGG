<?php

namespace Tests\Unit\Support\Ventas\Waitry;

use App\Support\Ventas\Waitry\WaitryConciliacionCircuitoSupport;
use PHPUnit\Framework\TestCase;

final class WaitryConciliacionCircuitoSupportTest extends TestCase
{
    public function test_importada_cobrada_requiere_importacion_y_pago_waitry(): void
    {
        $this->assertSame(
            WaitryConciliacionCircuitoSupport::CIRCUITO_TOTEM_IMPORTADA_COBRADA,
            WaitryConciliacionCircuitoSupport::resolverCircuito([
                'importada_erp' => true,
                'waitry_paid' => true,
                'anita_venta_id' => 10,
                'waitry_order_id' => 100,
            ]),
        );

        $this->assertNull(WaitryConciliacionCircuitoSupport::resolverCircuito([
            'importada_erp' => false,
            'waitry_paid' => true,
            'anita_venta_id' => 10,
        ]));
    }

    public function test_importada_impaga_waitry_usa_cuenta_erp(): void
    {
        $this->assertSame(
            WaitryConciliacionCircuitoSupport::CIRCUITO_IMPORTADA_IMPAGA_WAITRY,
            WaitryConciliacionCircuitoSupport::resolverCircuito([
                'importada_erp' => true,
                'waitry_paid' => false,
                'anita_venta_id' => 10,
                'waitry_total' => 500.0,
            ]),
        );

        $this->assertSame(
            WaitryConciliacionCircuitoSupport::CIRCUITO_TOTEM_IMPORTADA_IMPAGA_COBRADA_ANITA,
            WaitryConciliacionCircuitoSupport::resolverCircuito([
                'importada_erp' => true,
                'waitry_paid' => false,
                'anita_venta_id' => 10,
                'anita_cuentacaja_id' => 5,
                'waitry_total' => 500.0,
            ]),
        );
    }

    public function test_anita_factura_waitry_excluye_importadas(): void
    {
        $this->assertSame(
            WaitryConciliacionCircuitoSupport::CIRCUITO_ANITA_FACTURA_WAITRY,
            WaitryConciliacionCircuitoSupport::resolverCircuito([
                'importada_erp' => false,
                'anita_venta_id' => 20,
                'waitry_order_id' => 200,
                'waitry_paid' => true,
            ]),
        );

        $this->assertSame(
            WaitryConciliacionCircuitoSupport::CIRCUITO_TOTEM_IMPORTADA_COBRADA,
            WaitryConciliacionCircuitoSupport::resolverCircuito([
                'importada_erp' => true,
                'anita_venta_id' => 20,
                'waitry_order_id' => 200,
                'waitry_paid' => true,
            ]),
        );
    }

    public function test_resumen_por_circuito_suma_totales(): void
    {
        $filas = WaitryConciliacionCircuitoSupport::enriquecerFilas([
            [
                'importada_erp' => true,
                'waitry_paid' => true,
                'anita_venta_id' => 1,
                'waitry_total' => 100.0,
                'anita_total' => 100.0,
            ],
            [
                'importada_erp' => true,
                'waitry_paid' => false,
                'anita_venta_id' => 2,
                'waitry_total' => 80.0,
                'anita_total' => 80.0,
            ],
            [
                'importada_erp' => false,
                'anita_venta_id' => 3,
                'waitry_order_id' => 50,
                'waitry_total' => 50.0,
                'anita_total' => 50.0,
            ],
        ]);

        $resumen = WaitryConciliacionCircuitoSupport::resumenPorCircuito($filas);

        $this->assertSame(1, $resumen[WaitryConciliacionCircuitoSupport::CIRCUITO_TOTEM_IMPORTADA_COBRADA]['cantidad']);
        $this->assertSame(1, $resumen[WaitryConciliacionCircuitoSupport::CIRCUITO_IMPORTADA_IMPAGA_WAITRY]['cantidad']);
        $this->assertSame(80.0, $resumen[WaitryConciliacionCircuitoSupport::CIRCUITO_IMPORTADA_IMPAGA_WAITRY]['total_waitry']);
        $this->assertSame(1, $resumen[WaitryConciliacionCircuitoSupport::CIRCUITO_ANITA_FACTURA_WAITRY]['cantidad']);
    }
}

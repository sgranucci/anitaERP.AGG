<?php

namespace Tests\Unit\Support\Ventas\Waitry;

use App\Support\Ventas\Waitry\WaitryCierreJornadaDiscrepanciaSupport;
use App\Support\Ventas\Waitry\WaitryCierreJornadaVentanaSupport;
use PHPUnit\Framework\TestCase;

final class WaitryCierreJornadaVentanaSupportTest extends TestCase
{
    public function test_jornada_30_cerrada_31_usa_apertura_y_cierre_reales(): void
    {
        $resuelto = WaitryCierreJornadaVentanaSupport::resolverParaCierreJornada(
            '2026-05-30',
            '2026-05-30 18:30:00',
            '2026-05-31 07:15:00',
        );

        $ventana = $resuelto['ventana'];
        $rango = $resuelto['rango_calendario'];

        $this->assertSame('2026-05-30 18:30:00', $ventana['desde']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-31 07:15:00', $ventana['hasta']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-30', $rango['desde']);
        $this->assertSame('2026-05-31', $rango['hasta']);

        $ordenMadrugada = ['placed_at' => '2026-05-31 02:15:00'];
        $this->assertTrue(WaitryCierreJornadaVentanaSupport::ordenDentroVentanaOperativa(
            $ordenMadrugada,
            $ventana['desde'],
            $ventana['hasta'],
        ));

        $ordenAntesApertura = ['placed_at' => '2026-05-30 17:00:00'];
        $this->assertFalse(WaitryCierreJornadaVentanaSupport::ordenDentroVentanaOperativa(
            $ordenAntesApertura,
            $ventana['desde'],
            $ventana['hasta'],
        ));

        $ordenDespuesCierre = ['placed_at' => '2026-05-31 08:00:00'];
        $this->assertFalse(WaitryCierreJornadaVentanaSupport::ordenDentroVentanaOperativa(
            $ordenDespuesCierre,
            $ventana['desde'],
            $ventana['hasta'],
        ));

        $this->assertFalse(WaitryCierreJornadaVentanaSupport::ordenDentroVentanaOperativa(
            ['placed_at' => null],
            $ventana['desde'],
            $ventana['hasta'],
        ));
        $this->assertTrue(WaitryCierreJornadaVentanaSupport::perteneceTramoOrderId(100, 99));
        $this->assertFalse(WaitryCierreJornadaVentanaSupport::perteneceTramoOrderId(99, 99));
    }

    public function test_discrepancia_impaga_y_ok_no_discrepancia(): void
    {
        $this->assertTrue(WaitryCierreJornadaDiscrepanciaSupport::esDiscrepancia([
            'paid_waitry' => false,
            'importada_erp' => true,
        ]));

        $this->assertNull(WaitryCierreJornadaDiscrepanciaSupport::motivoDiscrepancia([
            'paid_waitry' => true,
            'importada_erp' => true,
            'facturada_erp' => true,
            'waitry_cobro_totem' => true,
            'fuente_listado' => 'getordersdetails',
        ]));
    }
}

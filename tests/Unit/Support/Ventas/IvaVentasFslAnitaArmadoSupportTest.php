<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\IvaVentas\IvaVentasFslAnitaArmadoSupport;
use App\Support\Ventas\IvaVentasListadoFiltros;
use PHPUnit\Framework\TestCase;

final class IvaVentasFslAnitaArmadoSupportTest extends TestCase
{
    public function test_arma_fila_exenta_letra_b(): void
    {
        $fila = [
            'ven_tipo' => 'FSL',
            'ven_letra' => 'B',
            'ven_sucursal' => '14',
            'ven_nro' => '6903',
            'ven_fecha' => '20260801',
            'ven_fecha_vto' => '20260801',
            'ven_monto' => '81952440.34',
            'ven_exento' => '81952440.34',
            'ven_nombre_cliente' => 'Venta maquinas',
        ];

        $reporte = IvaVentasFslAnitaArmadoSupport::filaReporte($fila, [
            'puntoventa_id' => 88,
            'puntoventa_codigo' => '0014',
            'puntoventa_nombre' => 'Máquinas',
            'sucursal' => 14,
            'tipotransaccion_id' => 12,
            'nombreempresa' => 'Rebisco',
        ], [
            'orden_fecha' => IvaVentasListadoFiltros::ORDEN_FECHA_JORNADA,
            'subdiario' => IvaVentasListadoFiltros::SUBDIARIO_VENTAS_A_B,
        ]);

        $this->assertNotNull($reporte);
        $this->assertSame('FSL', $reporte['tipo']);
        $this->assertSame('B', $reporte['letra']);
        $this->assertSame(6903, $reporte['numerocomprobante']);
        $this->assertSame('B0014-00006903', $reporte['comprobante']);
        $this->assertSame(0, $reporte['venta_id']);
        $this->assertSame('anita_fsl', $reporte['fuente']);
        $this->assertEqualsWithDelta(81952440.34, $reporte['columnas']['exento'], 0.01);
        $this->assertEqualsWithDelta(81952440.34, $reporte['columnas']['total'], 0.01);
        $this->assertEqualsWithDelta(0.0, $reporte['columnas']['iva'], 0.01);
        $this->assertSame('01/08/2026', $reporte['fecha_mov']);
    }

    public function test_reintegro_neto_conserva_signo_negativo(): void
    {
        $fila = [
            'ven_tipo' => 'FSL',
            'ven_letra' => 'B',
            'ven_sucursal' => '39',
            'ven_nro' => '7248',
            'ven_fecha' => '20260814',
            'ven_fecha_vto' => '20260814',
            'ven_monto' => '-58691547.80',
            'ven_exento' => '-58691547.80',
            'ven_nombre_cliente' => 'Venta maquinas',
        ];

        $reporte = IvaVentasFslAnitaArmadoSupport::filaReporte($fila, [
            'puntoventa_id' => 1,
            'puntoventa_codigo' => '0039',
            'puntoventa_nombre' => 'Máquinas',
            'sucursal' => 39,
            'tipotransaccion_id' => 12,
            'nombreempresa' => 'Test',
        ], [
            'orden_fecha' => IvaVentasListadoFiltros::ORDEN_FECHA_JORNADA,
            'subdiario' => IvaVentasListadoFiltros::SUBDIARIO_VENTAS_A_B,
        ]);

        $this->assertNotNull($reporte);
        $this->assertSame('B0039-00007248', $reporte['comprobante']);
        $this->assertEqualsWithDelta(-58691547.80, $reporte['columnas']['exento'], 0.01);
        $this->assertEqualsWithDelta(-58691547.80, $reporte['columnas']['total'], 0.01);
    }

    public function test_subdiario_solo_a_excluye_fsl(): void
    {
        $fila = [
            'ven_tipo' => 'FSL',
            'ven_sucursal' => '14',
            'ven_nro' => '1',
            'ven_fecha' => '20260801',
            'ven_exento' => '100',
            'ven_nombre_cliente' => 'Sala de máquinas',
        ];

        $this->assertNull(IvaVentasFslAnitaArmadoSupport::filaReporte($fila, [
            'puntoventa_id' => 1,
            'puntoventa_codigo' => '14',
            'puntoventa_nombre' => 'PV',
            'sucursal' => 14,
            'tipotransaccion_id' => 0,
            'nombreempresa' => '',
        ], [
            'subdiario' => IvaVentasListadoFiltros::SUBDIARIO_VENTAS_A,
        ]));
    }
}

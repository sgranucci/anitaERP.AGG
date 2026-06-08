<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\CierreJornadaProcesoAsientosPreviewSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoClasificacionSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoInsumoAjusteSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoMedioSupport;
use InvalidArgumentException;
use Tests\TestCase;

final class CierreJornadaProcesoInsumoAjusteSupportTest extends TestCase
{
    public function test_registrar_retorna_null_si_no_hay_lineas(): void
    {
        $result = CierreJornadaProcesoInsumoAjusteSupport::registrar(
            [],
            1,
            1,
            '2026-06-01',
            '2026-06-01',
            'Test',
            app(\App\Services\Stock\Articulo_MovimientoService::class),
        );

        $this->assertNull($result);
    }

    public function test_registrar_lanza_si_tipo_stock_no_configurado(): void
    {
        config(['gastronomia.cierre_jornada_tipotransaccion_stock_ajuste_consumo_id' => 0]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('GASTRONOMIA_CIERRE_JORNADA_TIPOTRANSACCION_STOCK_AJUSTE_CONSUMO_ID');

        CierreJornadaProcesoInsumoAjusteSupport::registrar(
            [['articulo_id' => 1, 'cantidad' => 1.]],
            1,
            1,
            '2026-06-01',
            '2026-06-01',
            'Test',
            app(\App\Services\Stock\Articulo_MovimientoService::class),
        );
    }

    public function test_monto_efectivo_no_facturado_toma_solo_parte_efectivo_del_plan(): void
    {
        $mov = [
            'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
            'total' => 1000.,
            'medios_pago_planificados' => [
                ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR, 'monto' => 600.],
                ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO, 'monto' => 400.],
            ],
        ];

        $this->assertSame(
            400.,
            CierreJornadaProcesoAsientosPreviewSupport::montoEfectivoNoFacturadoDesdeMov($mov),
        );
    }
}

final class CierreJornadaProcesoMediosCobroFacturaTest extends TestCase
{
    public function test_medios_planificados_cobranza_incluyen_qr_y_mp_excluyen_efectivo(): void
    {
        $mov = [
            'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
            'total' => 1000.,
            'medios_pago_planificados' => [
                ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR, 'monto' => 350.],
                ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_MP, 'monto' => 250.],
                ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO, 'monto' => 400.],
            ],
        ];

        $medios = CierreJornadaProcesoAsientosPreviewSupport::mediosPlanificadosCobranzaFacturaProceso($mov, 1);
        $claves = array_column($medios, 'clave');
        $montos = array_column($medios, 'monto');

        $this->assertContains(CierreJornadaProcesoMedioSupport::CLAVE_QR, $claves);
        $this->assertContains(CierreJornadaProcesoMedioSupport::CLAVE_MP, $claves);
        $this->assertNotContains(CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO, $claves);
        $this->assertSame(600., round(array_sum($montos), 2));
    }

    public function test_ajustar_montos_medios_cuadra_centavos(): void
    {
        $medios = [
            ['cuentacaja_id' => 1, 'moneda_id' => 1, 'monto' => 100.01, 'cotizacion' => 1., 'observacion' => ''],
            ['cuentacaja_id' => 2, 'moneda_id' => 1, 'monto' => 200.01, 'cotizacion' => 1., 'observacion' => ''],
        ];

        $ajustados = CierreJornadaProcesoAsientosPreviewSupport::ajustarMontosMediosCobroAlTotal($medios, 300.);

        $this->assertSame(300., round(array_sum(array_column($ajustados, 'monto')), 2));
    }
}

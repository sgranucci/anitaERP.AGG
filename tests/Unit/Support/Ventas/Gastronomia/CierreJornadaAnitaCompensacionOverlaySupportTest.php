<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\CierreJornadaAnitaCompensacionOverlaySupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoClasificacionSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoGrillaSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoMedioSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoRedistribucionSupport;
use Tests\TestCase;

final class CierreJornadaAnitaCompensacionOverlaySupportTest extends TestCase
{
    public function test_overlay_refleja_compensacion_efectivo_a_mp_en_fila_anita_jornada(): void
    {
        $totalesAnita = [
            'anita_jornada' => [
                'qr' => 0.0,
                'mp' => 500.0,
                'efectivo' => 500.0,
                'otros' => 0.0,
                'diferencia_caja' => 0.0,
                'total' => 1000.0,
                'tipo' => 'anita_jornada',
            ],
            'total' => 1000.0,
        ];

        $movimientos = [
            [
                'waitry_order_id' => 1,
                'total' => 1000.0,
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
                'medio_waitry_clave' => CierreJornadaProcesoMedioSupport::CLAVE_MP,
            ],
            [
                'venta_id' => 99,
                'total' => 50.0,
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_FACTURADO_MEDIO_REAL,
                'medio_anita_clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO,
                'facturada_erp' => true,
                'anita_compensacion_redistribucion' => true,
                'medios_pago_planificados' => [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_MP, 'monto' => 50.0],
                ],
            ],
        ];

        $redistribucion = CierreJornadaProcesoRedistribucionSupport::aplicar($movimientos, 1000.0, 5.0);
        $totalesAjustados = CierreJornadaAnitaCompensacionOverlaySupport::aplicarTotalesAnita(
            $totalesAnita,
            $redistribucion['movimientos'],
            1,
        );

        $cuadro = CierreJornadaProcesoGrillaSupport::armar($redistribucion['movimientos'], $totalesAjustados, 1);

        $this->assertSame(550.0, $cuadro['filas'][0]['mp']);
        $this->assertSame(450.0, $cuadro['filas'][0]['efectivo']);
        $this->assertSame(1000.0, $cuadro['filas'][0]['total']);
    }

    public function test_compensacion_no_baja_total_si_falta_cuenta_destino(): void
    {
        $totalesAnita = [
            'anita_jornada' => [
                'qr' => 0.0,
                'mp' => 500.0,
                'efectivo' => 500.0,
                'otros' => 0.0,
                'diferencia_caja' => 0.0,
                'total' => 1000.0,
                'tipo' => 'anita_jornada',
                'por_cuenta' => [
                    10 => 500.0,
                    20 => 500.0,
                ],
            ],
            'total' => 1000.0,
        ];

        $movimientos = [
            [
                'venta_id' => 99,
                'total' => 50.0,
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_FACTURADO_MEDIO_REAL,
                'medio_anita_clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO,
                'facturada_erp' => true,
                'anita_compensacion_redistribucion' => true,
                'medios_pago_planificados' => [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_MP, 'monto' => 50.0],
                ],
            ],
        ];

        $redistribucion = CierreJornadaProcesoRedistribucionSupport::aplicar($movimientos, 1000.0, 5.0);
        $totalesAjustados = CierreJornadaAnitaCompensacionOverlaySupport::aplicarTotalesAnita(
            $totalesAnita,
            $redistribucion['movimientos'],
            99999,
        );

        $this->assertSame(1000.0, $totalesAjustados['anita_jornada']['total']);
        $this->assertSame(500.0, $totalesAjustados['anita_jornada']['efectivo']);
        $this->assertSame(500.0, $totalesAjustados['anita_jornada']['mp']);
    }

    public function test_compensacion_con_por_cuenta_mantiene_total_tras_enriquecer_filas(): void
    {
        $totalesAnita = [
            'anita_jornada' => [
                'qr' => 0.0,
                'mp' => 500.0,
                'efectivo' => 500.0,
                'otros' => 0.0,
                'diferencia_caja' => 0.0,
                'total' => 1000.0,
                'tipo' => 'anita_jornada',
                'por_cuenta' => [
                    10 => 500.0,
                    20 => 500.0,
                ],
            ],
            'total' => 1000.0,
        ];

        $movimientos = [
            [
                'waitry_order_id' => 1,
                'total' => 1000.0,
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
                'medio_waitry_clave' => CierreJornadaProcesoMedioSupport::CLAVE_MP,
            ],
            [
                'venta_id' => 99,
                'total' => 50.0,
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_FACTURADO_MEDIO_REAL,
                'medio_anita_clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO,
                'facturada_erp' => true,
                'anita_compensacion_redistribucion' => true,
                'medios_pago_planificados' => [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_MP, 'monto' => 50.0],
                ],
            ],
        ];

        $redistribucion = CierreJornadaProcesoRedistribucionSupport::aplicar($movimientos, 1000.0, 5.0);
        $totalesAjustados = CierreJornadaAnitaCompensacionOverlaySupport::aplicarTotalesAnita(
            $totalesAnita,
            $redistribucion['movimientos'],
            1,
        );

        $clasificacion = CierreJornadaProcesoClasificacionSupport::clasificar(
            $redistribucion['movimientos'],
            1,
            $totalesAjustados,
        );

        $this->assertSame(1000.0, $clasificacion['cuadro_filas'][0]['total']);
        $this->assertSame(450.0, $clasificacion['cuadro_filas'][0]['efectivo']);
        $this->assertSame(550.0, $clasificacion['cuadro_filas'][0]['mp']);
    }

    public function test_compensaciones_por_venta_detecta_traslado_a_mp(): void
    {
        $movimientos = [
            [
                'venta_id' => 99,
                'total' => 50.0,
                'medio_anita_clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO,
                'facturada_erp' => true,
                'medios_pago_planificados' => [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_MP, 'monto' => 50.0],
                ],
            ],
        ];

        $comp = CierreJornadaAnitaCompensacionOverlaySupport::compensacionesPorVenta($movimientos);

        $this->assertCount(1, $comp);
        $this->assertSame(99, $comp[0]['venta_id']);
        $this->assertSame(CierreJornadaProcesoMedioSupport::CLAVE_MP, $comp[0]['traslados'][0]['hacia']);
        $this->assertSame(50.0, $comp[0]['traslados'][0]['monto']);
    }
}

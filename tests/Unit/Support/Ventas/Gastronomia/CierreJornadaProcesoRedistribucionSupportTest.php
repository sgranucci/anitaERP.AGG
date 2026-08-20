<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\CierreJornadaProcesoClasificacionSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoMedioSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoRedistribucionSupport;
use Tests\TestCase;

class CierreJornadaProcesoRedistribucionSupportTest extends TestCase
{
    public function test_sin_facturar_qr_pasa_a_efectivo_hasta_objetivo(): void
    {
        $movimientos = [
            [
                'waitry_order_id' => 10,
                'total' => 500.0,
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
                'medio_waitry_clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR,
            ],
            [
                'waitry_order_id' => 11,
                'total' => 400.0,
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
                'medio_waitry_clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR,
            ],
        ];

        $resultado = CierreJornadaProcesoRedistribucionSupport::aplicar($movimientos, 10000.0, 5.0);

        $this->assertSame(500.0, $resultado['objetivo_importe']);
        $this->assertSame(500.0, $resultado['asignado_sin_facturar_a_efectivo']);
        $this->assertSame(
            CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO,
            $resultado['movimientos'][0]['medio_pago_planificado'],
        );
        $this->assertNull($resultado['movimientos'][1]['medio_pago_planificado'] ?? null);
    }

    public function test_objetivo_redondea_a_pesos_enteros(): void
    {
        $objetivo = CierreJornadaProcesoRedistribucionSupport::objetivoDesdePorcentaje(3752242.14, 5.0);

        $this->assertSame(187612.0, $objetivo);
    }

    public function test_mixto_parcial_sin_centavos(): void
    {
        [$efectivo, $qr] = CierreJornadaProcesoRedistribucionSupport::partesMixtoEfectivoQr(7600.0, 6912.11);

        $this->assertSame(6912.0, $efectivo);
        $this->assertSame(688.0, $qr);
        $this->assertSame(7600.0, $efectivo + $qr);
    }

    public function test_compensa_mismo_importe_efectivo_a_qr_en_facturado_anita(): void
    {
        $movimientos = [
            [
                'waitry_order_id' => 1,
                'total' => 600.0,
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
                'medio_waitry_clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR,
            ],
            [
                'waitry_order_id' => 99,
                'venta_codigo' => 'FAC 001',
                'total' => 400.0,
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_FACTURADO_MEDIO_REAL,
                'medio_anita_clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO,
                'facturada_erp' => true,
            ],
            [
                'waitry_order_id' => 100,
                'venta_codigo' => 'FAC 002',
                'total' => 200.0,
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_FACTURADO_MEDIO_REAL,
                'medio_anita_clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO,
                'facturada_erp' => true,
            ],
        ];

        $resultado = CierreJornadaProcesoRedistribucionSupport::aplicar($movimientos, 10000.0, 10.0);

        $this->assertSame(1000.0, $resultado['objetivo_importe']);
        $this->assertSame(600.0, $resultado['asignado_sin_facturar_a_efectivo']);
        $this->assertSame(600.0, $resultado['asignado_facturado_efectivo_a_qr']);
        $this->assertSame(0.0, $resultado['asignado_facturado_efectivo_a_mp']);
        $this->assertSame(600.0, $resultado['asignado_efectivo_por_medio_origen']['qr']);
        $this->assertTrue($resultado['cuadre_qr_z_ok']);
        $this->assertSame(
            CierreJornadaProcesoMedioSupport::CLAVE_QR,
            $resultado['movimientos'][1]['medio_pago_planificado'],
        );
    }

    public function test_compensa_efectivo_a_mp_cuando_sin_facturar_es_mp(): void
    {
        $movimientos = [
            [
                'waitry_order_id' => 1,
                'total' => 1000.0,
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
                'medio_waitry_clave' => CierreJornadaProcesoMedioSupport::CLAVE_MP,
            ],
            [
                'waitry_order_id' => 99,
                'venta_codigo' => 'FAC 001',
                'total' => 50.0,
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_FACTURADO_MEDIO_REAL,
                'medio_anita_clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO,
                'facturada_erp' => true,
            ],
        ];

        $resultado = CierreJornadaProcesoRedistribucionSupport::aplicar($movimientos, 1000.0, 5.0);

        $this->assertSame(50.0, $resultado['objetivo_importe']);
        $this->assertSame(50.0, $resultado['asignado_sin_facturar_a_efectivo']);
        $this->assertSame(0.0, $resultado['asignado_facturado_efectivo_a_qr']);
        $this->assertSame(50.0, $resultado['asignado_facturado_efectivo_a_mp']);
        $this->assertSame(50.0, $resultado['asignado_efectivo_por_medio_origen']['mp']);
        $this->assertTrue($resultado['cuadre_qr_z_ok']);
        $this->assertSame(
            CierreJornadaProcesoMedioSupport::CLAVE_MP,
            $resultado['movimientos'][1]['medio_pago_planificado'],
        );
        $this->assertSame('facturado_efectivo_a_mp', $resultado['ajustes'][1]['tipo']);
        $this->assertSame('mixto', $resultado['movimientos'][0]['medio_pago_planificado']);
        $this->assertSame(50.0, $resultado['movimientos'][0]['medios_pago_planificados'][0]['monto']);
        $this->assertSame(950.0, $resultado['movimientos'][0]['medios_pago_planificados'][1]['monto']);
    }

    public function test_sin_facturar_mp_pasa_a_efectivo_hasta_objetivo(): void
    {
        $movimientos = [
            [
                'waitry_order_id' => 20,
                'total' => 300.0,
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
                'medio_waitry_clave' => CierreJornadaProcesoMedioSupport::CLAVE_MP,
            ],
        ];

        $resultado = CierreJornadaProcesoRedistribucionSupport::aplicar($movimientos, 10000.0, 3.0);

        $this->assertSame(300.0, $resultado['objetivo_importe']);
        $this->assertSame(300.0, $resultado['asignado_sin_facturar_a_efectivo']);
        $this->assertSame(
            CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO,
            $resultado['movimientos'][0]['medio_pago_planificado'],
        );
        $this->assertSame('sin_facturar_mp_a_efectivo', $resultado['ajustes'][0]['tipo']);
    }

    public function test_facturado_totem_qr_pasa_a_efectivo_en_redistribucion(): void
    {
        $movimientos = [
            [
                'waitry_order_id' => 2,
                'total' => 100.0,
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_FACTURADO_TOTEM,
                'medio_waitry_clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR,
                'facturada_erp' => true,
            ],
        ];

        $resultado = CierreJornadaProcesoRedistribucionSupport::aplicar($movimientos, 1000.0, 10.0);

        $this->assertSame(100.0, $resultado['objetivo_importe']);
        $this->assertSame(100.0, $resultado['asignado_sin_facturar_a_efectivo']);
        $this->assertSame(
            CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO,
            $resultado['movimientos'][0]['medio_pago_planificado'],
        );
    }

    public function test_total_sin_facturar_recodificable_solo_qr_y_mp(): void
    {
        $movimientos = [
            [
                'waitry_order_id' => 1,
                'total' => 100.0,
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
                'medio_waitry_clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR,
            ],
            [
                'waitry_order_id' => 2,
                'total' => 50.0,
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
                'medio_waitry_clave' => CierreJornadaProcesoMedioSupport::CLAVE_MP,
            ],
            [
                'waitry_order_id' => 3,
                'total' => 20.0,
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
                'medio_waitry_clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO,
            ],
        ];

        $this->assertSame(150.0, CierreJornadaProcesoRedistribucionSupport::totalSinFacturarRecodificable($movimientos));
        $this->assertSame(15.0, CierreJornadaProcesoRedistribucionSupport::porcentajeMaximoSobreFacturacion(1000.0, 150.0));
    }

    public function test_validar_porcentaje_rechaza_si_objetivo_supera_recodificable(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CierreJornadaProcesoRedistribucionSupport::validarPorcentajeNoExcedeRecodificable(10000.0, 10.0, 500.0);
    }

    public function test_validar_porcentaje_acepta_dentro_del_tope(): void
    {
        CierreJornadaProcesoRedistribucionSupport::validarPorcentajeNoExcedeRecodificable(10000.0, 5.0, 500.0);

        $this->assertTrue(true);
    }

    public function test_porcentaje_aplicar_usa_objetivo_si_hay_disponible(): void
    {
        $this->assertSame(25.0, CierreJornadaProcesoRedistribucionSupport::porcentajeAplicar(25.0, 30.4094));
    }

    public function test_porcentaje_aplicar_capea_al_disponible_cuando_es_menor(): void
    {
        $this->assertSame(18.459, CierreJornadaProcesoRedistribucionSupport::porcentajeAplicar(25.0, 18.459));
    }

    public function test_porcentaje_aplicar_sin_disponible_es_cero(): void
    {
        $this->assertSame(0.0, CierreJornadaProcesoRedistribucionSupport::porcentajeAplicar(25.0, 0.0));
        $this->assertSame(0.0, CierreJornadaProcesoRedistribucionSupport::porcentajeAplicar(0.0, 18.459));
    }
}

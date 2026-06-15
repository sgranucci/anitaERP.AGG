<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Models\Ventas\GastronomiaCierreJornadaProcesoSnapshot;
use App\Models\Ventas\JornadaGastronomia;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoJornadaSupport;
use Carbon\Carbon;
use Tests\TestCase;

final class CierreJornadaProcesoJornadaSupportTest extends TestCase
{
    public function test_contexto_jornada_abierta_bloquea_factura(): void
    {
        $jornada = new JornadaGastronomia([
            'estado' => JornadaGastronomia::ESTADO_ABIERTA,
        ]);

        $ctx = CierreJornadaProcesoJornadaSupport::contexto($jornada);

        $this->assertTrue($ctx['abierta']);
        $this->assertTrue($ctx['factura_bloqueada']);
        $this->assertFalse($ctx['puede_facturar_proceso']);
        $this->assertSame('auditoria_en_curso', $ctx['modo']);
    }

    public function test_snapshot_provisional_invalida_al_cerrar_jornada(): void
    {
        $jornada = new JornadaGastronomia([
            'estado' => JornadaGastronomia::ESTADO_CERRADA,
            'cierre_en' => Carbon::parse('2026-06-03 22:00:00'),
        ]);

        $snapshot = new GastronomiaCierreJornadaProcesoSnapshot([
            'payload' => [
                'jornada_estado' => JornadaGastronomia::ESTADO_ABIERTA,
                'snapshot_provisional' => true,
            ],
        ]);

        $this->assertTrue(CierreJornadaProcesoJornadaSupport::debeInvalidarSnapshot($jornada, $snapshot));
    }

    public function test_snapshot_vacio_con_cierre_totem_con_lineas_debe_invalidar(): void
    {
        $jornada = new JornadaGastronomia([
            'estado' => JornadaGastronomia::ESTADO_CERRADA,
            'cierre_en' => Carbon::parse('2026-06-03 22:00:00'),
        ]);
        $jornada->setRelation('cierreTotem', new \App\Models\Ventas\CierreTotemJornadaGastronomia([
            'cantidad_lineas' => 370,
        ]));

        $snapshot = new GastronomiaCierreJornadaProcesoSnapshot([
            'payload' => [
                'jornada_estado' => JornadaGastronomia::ESTADO_CERRADA,
                'snapshot_provisional' => false,
                'lineas_schema_version' => CierreJornadaProcesoJornadaSupport::LINEAS_SCHEMA_VERSION,
                'lineas' => [],
            ],
        ]);

        $this->assertTrue(CierreJornadaProcesoJornadaSupport::debeInvalidarSnapshot($jornada, $snapshot));
    }

    public function test_snapshot_legacy_sin_schema_version_debe_invalidar(): void
    {
        $jornada = new JornadaGastronomia([
            'estado' => JornadaGastronomia::ESTADO_CERRADA,
            'cierre_en' => Carbon::parse('2026-06-03 22:00:00'),
        ]);

        $snapshot = new GastronomiaCierreJornadaProcesoSnapshot([
            'payload' => [
                'jornada_estado' => JornadaGastronomia::ESTADO_CERRADA,
                'snapshot_provisional' => false,
            ],
        ]);

        $this->assertTrue(CierreJornadaProcesoJornadaSupport::debeInvalidarSnapshot($jornada, $snapshot));
    }

    public function test_snapshot_con_schema_version_actual_no_invalida_por_version(): void
    {
        $jornada = new JornadaGastronomia([
            'estado' => JornadaGastronomia::ESTADO_CERRADA,
            'cierre_en' => Carbon::parse('2026-06-03 22:00:00'),
        ]);

        $snapshot = new GastronomiaCierreJornadaProcesoSnapshot([
            'payload' => [
                'jornada_estado' => JornadaGastronomia::ESTADO_CERRADA,
                'snapshot_provisional' => false,
                'lineas_schema_version' => CierreJornadaProcesoJornadaSupport::LINEAS_SCHEMA_VERSION,
            ],
        ]);

        $this->assertFalse(CierreJornadaProcesoJornadaSupport::debeInvalidarSnapshot($jornada, $snapshot));
    }

    public function test_contexto_cerrada_con_snapshot_definitivo_permite_facturar(): void
    {
        $jornada = new JornadaGastronomia([
            'estado' => JornadaGastronomia::ESTADO_CERRADA,
            'cierre_en' => Carbon::parse('2026-06-03 22:00:00'),
        ]);

        $snapshot = new GastronomiaCierreJornadaProcesoSnapshot([
            'payload' => [
                'jornada_estado' => JornadaGastronomia::ESTADO_CERRADA,
                'snapshot_provisional' => false,
            ],
        ]);

        $ctx = CierreJornadaProcesoJornadaSupport::contexto($jornada, $snapshot);

        $this->assertTrue($ctx['cerrada']);
        $this->assertTrue($ctx['puede_facturar_proceso']);
        $this->assertSame('auditoria_definitiva', $ctx['modo']);
    }

    public function test_assert_emitir_factura_lanza_si_jornada_abierta(): void
    {
        $jornada = new JornadaGastronomia([
            'estado' => JornadaGastronomia::ESTADO_ABIERTA,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('jornada gastronomía sigue abierta');

        CierreJornadaProcesoJornadaSupport::assertPuedeEmitirFacturaProceso($jornada);
    }

    public function test_assert_emitir_factura_no_lanza_si_puede_facturar(): void
    {
        $jornada = new JornadaGastronomia([
            'estado' => JornadaGastronomia::ESTADO_CERRADA,
            'cierre_en' => Carbon::parse('2026-06-03 22:00:00'),
        ]);

        $snapshot = new GastronomiaCierreJornadaProcesoSnapshot([
            'payload' => [
                'jornada_estado' => JornadaGastronomia::ESTADO_CERRADA,
                'snapshot_provisional' => false,
            ],
        ]);

        CierreJornadaProcesoJornadaSupport::assertPuedeEmitirFacturaProceso($jornada, $snapshot);
        $this->addToAssertionCount(1);
    }

    public function test_contexto_bloquea_reemision_si_factura_ya_emitida(): void
    {
        $jornada = new JornadaGastronomia([
            'estado' => JornadaGastronomia::ESTADO_CERRADA,
            'cierre_en' => Carbon::parse('2026-06-03 22:00:00'),
        ]);

        $snapshot = new GastronomiaCierreJornadaProcesoSnapshot([
            'payload' => [
                'jornada_estado' => JornadaGastronomia::ESTADO_CERRADA,
                'snapshot_provisional' => false,
                'factura_proceso_emision' => [
                    'emitido_en' => '2026-06-04T10:00:00+00:00',
                    'facturas' => [
                        ['lote' => 1, 'venta_id' => 100, 'factura' => 'FAC B 00020-183681', 'total' => 50000.],
                    ],
                    'total_factura' => 50000.,
                ],
            ],
        ]);

        $ctx = CierreJornadaProcesoJornadaSupport::contexto($jornada, $snapshot);

        $this->assertTrue($ctx['factura_proceso_emitida']);
        $this->assertFalse($ctx['puede_facturar_proceso']);
        $this->assertTrue($ctx['factura_bloqueada']);
        $this->assertStringContainsString('Ya se emitió', (string) $ctx['motivo_factura_bloqueada']);
    }

    public function test_contexto_proceso_completado_con_resultado_grabado(): void
    {
        $jornada = new JornadaGastronomia([
            'estado' => JornadaGastronomia::ESTADO_CERRADA,
            'cierre_en' => Carbon::parse('2026-06-03 22:00:00'),
        ]);

        $snapshot = new GastronomiaCierreJornadaProcesoSnapshot([
            'payload' => [
                'jornada_estado' => JornadaGastronomia::ESTADO_CERRADA,
                'snapshot_provisional' => false,
                'factura_proceso_emision' => [
                    'emitido_en' => '2026-06-04T10:00:00+00:00',
                    'porcentaje' => 12.5,
                    'facturas' => [
                        ['lote' => 1, 'venta_id' => 100, 'factura' => 'FAC B 00020-183681', 'total' => 50000., 'cantidad_comandas' => 5],
                    ],
                    'total_factura' => 50000.,
                    'total_ajuste' => 1000.,
                ],
                'asientos_proceso_grabacion' => [
                    'grabado_en' => '2026-06-04T11:00:00+00:00',
                    'asientos' => [
                        ['codigo' => 'AS1', 'titulo' => 'Ventas', 'asiento_id' => 50, 'numeroasiento' => '20260604001', 'resumen_debe' => 100., 'resumen_haber' => 100.],
                    ],
                ],
                'rendicion_proceso_anita' => [
                    'nro_oper' => 12345,
                    'grabado_en' => '2026-06-04T11:05:00+00:00',
                ],
            ],
        ]);

        $ctx = CierreJornadaProcesoJornadaSupport::contexto($jornada, $snapshot);

        $this->assertTrue($ctx['proceso_cierre_completado']);
        $this->assertTrue($ctx['asientos_grabados']);
        $this->assertTrue($ctx['puede_revertir_proceso']);
        $this->assertFalse($ctx['puede_grabar_asientos_proceso']);
        $this->assertCount(1, $ctx['resultado_grabado']['facturas']);
        $this->assertSame('FAC B 00020-183681', $ctx['resultado_grabado']['facturas'][0]['factura']);
        $this->assertCount(1, $ctx['resultado_grabado']['asientos']);
        $this->assertSame(12.5, $ctx['resultado_grabado']['porcentaje']);
    }

    public function test_contexto_permite_reintentar_rendicion_anita_si_asientos_grabados(): void
    {
        $jornada = new JornadaGastronomia([
            'estado' => JornadaGastronomia::ESTADO_CERRADA,
            'cierre_en' => Carbon::parse('2026-06-03 22:00:00'),
        ]);

        $snapshot = new GastronomiaCierreJornadaProcesoSnapshot([
            'payload' => [
                'jornada_estado' => JornadaGastronomia::ESTADO_CERRADA,
                'snapshot_provisional' => false,
                'factura_proceso_emision' => [
                    'emitido_en' => '2026-06-04T10:00:00+00:00',
                    'facturas' => [
                        ['lote' => 1, 'venta_id' => 100, 'factura' => 'FAC B 00020-183681', 'total' => 50000.],
                    ],
                ],
                'asientos_proceso_grabacion' => [
                    'asientos' => [
                        ['codigo' => 'AS1', 'asiento_id' => 50],
                    ],
                ],
            ],
        ]);

        $ctx = CierreJornadaProcesoJornadaSupport::contexto($jornada, $snapshot);

        $this->assertTrue($ctx['asientos_grabados']);
        $this->assertTrue($ctx['rendicion_anita_pendiente']);
        $this->assertFalse($ctx['proceso_cierre_completado']);
        $this->assertTrue($ctx['puede_grabar_asientos_proceso']);
    }

    public function test_resultado_grabado_desde_payload_legacy_venta_unica(): void
    {
        $resultado = CierreJornadaProcesoJornadaSupport::resultadoGrabadoDesdePayload([
            'factura_proceso_emision' => [
                'venta_id' => 99,
                'factura' => 'FAC B 00020-100',
                'emitido_en' => '2026-06-04T10:00:00+00:00',
            ],
        ]);

        $this->assertCount(1, $resultado['facturas']);
        $this->assertSame(99, $resultado['facturas'][0]['venta_id']);
        $this->assertSame('FAC B 00020-100', $resultado['facturas'][0]['factura']);
    }

    public function test_contexto_sin_comandas_waitry_permite_grabar_asientos_sin_factura(): void
    {
        $jornada = new JornadaGastronomia([
            'estado' => JornadaGastronomia::ESTADO_CERRADA,
            'cierre_en' => Carbon::parse('2026-06-08 22:00:00'),
        ]);

        $snapshot = new GastronomiaCierreJornadaProcesoSnapshot([
            'payload' => [
                'jornada_estado' => JornadaGastronomia::ESTADO_CERRADA,
                'snapshot_provisional' => false,
                'requiere_emision_proceso' => false,
                'factura_proceso_emision' => CierreJornadaProcesoJornadaSupport::emisionOmitidaPayload(0.),
            ],
        ]);

        $ctx = CierreJornadaProcesoJornadaSupport::contexto($jornada, $snapshot);

        $this->assertFalse($ctx['requiere_emision_proceso']);
        $this->assertTrue($ctx['factura_proceso_omitida']);
        $this->assertFalse($ctx['puede_facturar_proceso']);
        $this->assertTrue($ctx['puede_grabar_asientos_proceso']);
        $this->assertStringContainsString('asientos contables', (string) $ctx['motivo_factura_bloqueada']);
    }

    public function test_requiere_emision_proceso_false_sin_movimientos_facturables(): void
    {
        $this->assertFalse(CierreJornadaProcesoJornadaSupport::requiereEmisionProcesoDesdeMovimientos([]));
    }

    public function test_resolver_porcentaje_no_exige_recalculo_si_no_requiere_emision(): void
    {
        $snapshot = new GastronomiaCierreJornadaProcesoSnapshot([
            'payload' => [
                'requiere_emision_proceso' => false,
            ],
        ]);

        $pct = CierreJornadaProcesoJornadaSupport::resolverPorcentajeOperacion($snapshot, 0., true);

        $this->assertSame(0., $pct);
    }

    public function test_resolver_porcentaje_acepta_cero_tras_recalculo_aplicado(): void
    {
        $snapshot = new GastronomiaCierreJornadaProcesoSnapshot([
            'porcentaje' => 0.,
            'payload' => [
                'requiere_emision_proceso' => true,
                'porcentaje' => 0.,
                'recalculo_aplicado_en' => '2026-06-08T10:00:00+00:00',
            ],
        ]);

        $pct = CierreJornadaProcesoJornadaSupport::resolverPorcentajeOperacion($snapshot, 0., true);

        $this->assertSame(0., $pct);
    }
}

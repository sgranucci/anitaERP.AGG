<?php

namespace Tests\Unit\Support\Ventas\Waitry;

use App\Support\Ventas\Waitry\WaitryInformeZConciliacionSupport;
use App\Support\Ventas\Waitry\WaitryMedioPagoCuentacajaSupport;
use Tests\TestCase;

final class WaitryInformeZConciliacionSupportTest extends TestCase
{
    public function test_conciliar_ok_cuando_montos_coinciden(): void
    {
        $plantilla = [
            [
                'totem_id' => 1,
                'ubicacion_nombre' => 'Barra',
                'lineas' => [
                    [
                        'tipo_waitry' => 'mercadopago',
                        'etiqueta' => 'Mercado Pago',
                        'monto_sistema' => 100.0,
                        'cantidad_sistema' => 2,
                        'monto_informe_z' => 100.0,
                    ],
                ],
            ],
        ];

        $resultado = WaitryInformeZConciliacionSupport::conciliar($plantilla);

        $this->assertTrue($resultado['ok']);
        $this->assertTrue($resultado['totems'][0]['ok']);
        $this->assertSame(0.0, $resultado['totems'][0]['lineas'][0]['diferencia']);
    }

    public function test_conciliar_falla_con_diferencia_fuera_de_tolerancia(): void
    {
        $plantilla = [
            [
                'totem_id' => 2,
                'ubicacion_nombre' => 'Terraza',
                'lineas' => [
                    [
                        'tipo_waitry' => 'cash',
                        'etiqueta' => 'Efectivo',
                        'monto_sistema' => 50.0,
                        'cantidad_sistema' => 1,
                        'monto_informe_z' => 48.0,
                    ],
                ],
            ],
        ];

        $resultado = WaitryInformeZConciliacionSupport::conciliar($plantilla);

        $this->assertFalse($resultado['ok']);
        $this->assertFalse($resultado['totems'][0]['ok']);
        $this->assertSame(-2.0, $resultado['totems'][0]['lineas'][0]['diferencia']);
    }

    public function test_fusionar_informe_z_en_plantilla(): void
    {
        $plantilla = [
            [
                'totem_id' => 3,
                'lineas' => [
                    [
                        'tipo_waitry' => 'totem',
                        'monto_sistema' => 10.0,
                        'monto_informe_z' => null,
                    ],
                ],
            ],
        ];

        $informeZ = [
            'totems' => [
                [
                    'totem_id' => 3,
                    'lineas' => [
                        ['tipo_waitry' => 'totem', 'monto' => 10.5],
                    ],
                ],
            ],
        ];

        $fusionada = WaitryInformeZConciliacionSupport::fusionarInformeZEnPlantilla($plantilla, $informeZ);

        $this->assertSame(10.5, $fusionada[0]['lineas'][0]['monto_informe_z']);
    }

    public function test_fusionar_informe_z_en_plantilla_por_table_id(): void
    {
        $plantilla = [
            [
                'totem_id' => 2,
                'waitry_table_id' => 103443,
                'lineas' => [
                    [
                        'tipo_waitry' => 'credit_card',
                        'cuentacaja_id' => 226,
                        'monto_sistema' => 100.0,
                        'monto_informe_z' => null,
                    ],
                ],
            ],
            [
                'totem_id' => 2,
                'waitry_table_id' => 103444,
                'lineas' => [
                    [
                        'tipo_waitry' => 'credit_card',
                        'cuentacaja_id' => 226,
                        'monto_sistema' => 50.0,
                        'monto_informe_z' => null,
                    ],
                ],
            ],
        ];

        $informeZ = [
            'totems' => [
                [
                    'totem_id' => 2,
                    'waitry_table_id' => 103443,
                    'lineas' => [
                        ['tipo_waitry' => 'credit_card', 'cuentacaja_id' => 226, 'monto' => 99.0],
                    ],
                ],
                [
                    'totem_id' => 2,
                    'waitry_table_id' => 103444,
                    'lineas' => [
                        ['tipo_waitry' => 'credit_card', 'cuentacaja_id' => 226, 'monto' => 48.0],
                    ],
                ],
            ],
        ];

        $fusionada = WaitryInformeZConciliacionSupport::fusionarInformeZEnPlantilla($plantilla, $informeZ, 1);

        $this->assertSame(99.0, $fusionada[0]['lineas'][0]['monto_informe_z']);
        $this->assertSame(48.0, $fusionada[1]['lineas'][0]['monto_informe_z']);
    }

    public function test_fusionar_plantilla_unificada_no_asigna_suma_cuenta_a_otra_categoria(): void
    {
        $cuentaGmep = 201;
        $plantilla = [
            [
                'totem_id' => WaitryInformeZConciliacionSupport::TOTEM_ID_PLANTILLA_UNIFICADA,
                'plantilla_unificada' => true,
                'lineas' => [
                    [
                        'tipo_waitry' => WaitryMedioPagoCuentacajaSupport::CATEGORIA_MERCADOPAGO,
                        'cuentacaja_id' => $cuentaGmep,
                        'monto_sistema' => 2600.0,
                        'monto_informe_z' => null,
                    ],
                    [
                        'tipo_waitry' => WaitryMedioPagoCuentacajaSupport::CATEGORIA_POSNET_KIOSCO,
                        'cuentacaja_id' => $cuentaGmep,
                        'monto_sistema' => 540900.0,
                        'monto_informe_z' => null,
                    ],
                    [
                        'tipo_waitry' => WaitryMedioPagoCuentacajaSupport::CATEGORIA_QR_KIOSCO,
                        'cuentacaja_id' => $cuentaGmep,
                        'monto_sistema' => 214100.0,
                        'monto_informe_z' => null,
                    ],
                ],
            ],
        ];

        $informeZ = [
            'totems' => [
                [
                    'totem_id' => 0,
                    'lineas' => [
                        [
                            'tipo_waitry' => WaitryMedioPagoCuentacajaSupport::CATEGORIA_POSNET_KIOSCO,
                            'cuentacaja_id' => $cuentaGmep,
                            'monto' => 540900.0,
                        ],
                        [
                            'tipo_waitry' => WaitryMedioPagoCuentacajaSupport::CATEGORIA_QR_KIOSCO,
                            'cuentacaja_id' => $cuentaGmep,
                            'monto' => 214100.0,
                        ],
                    ],
                ],
            ],
        ];

        $fusionada = WaitryInformeZConciliacionSupport::fusionarInformeZEnPlantilla($plantilla, $informeZ, 1);

        $this->assertNull($fusionada[0]['lineas'][0]['monto_informe_z']);
        $this->assertSame(540900.0, $fusionada[0]['lineas'][1]['monto_informe_z']);
        $this->assertSame(214100.0, $fusionada[0]['lineas'][2]['monto_informe_z']);

        $conciliacion = WaitryInformeZConciliacionSupport::conciliar($fusionada);
        $this->assertSame(540900.0, $conciliacion['totems'][0]['lineas'][1]['monto_informe_z']);
        $this->assertNotSame(755000.0, $conciliacion['totems'][0]['lineas'][0]['monto_informe_z']);
    }

    public function test_expandir_bloque_plantilla_por_table_ids(): void
    {
        config(['waitry.tipo_pago_cuentacaja' => ['credit_card' => 226]]);

        $totem = new \App\Models\Ventas\TotemWaitryGastronomia;
        $totem->id = 2;
        $totem->waitry_table_id = 103443;
        $totem->waitry_table_ids_adicionales = '103444';

        $sistema = [
            'por_table_id' => [
                [
                    'waitry_table_id' => 103443,
                    'total_ingreso' => 100.0,
                    'por_medio_pago' => [
                        ['tipo' => 'credit_card', 'cantidad' => 1, 'total' => 100.0],
                    ],
                ],
                [
                    'waitry_table_id' => 103444,
                    'total_ingreso' => 50.0,
                    'por_medio_pago' => [
                        ['tipo' => 'credit_card', 'cantidad' => 1, 'total' => 50.0],
                    ],
                ],
            ],
        ];

        $ref = new \ReflectionClass(WaitryInformeZConciliacionSupport::class);
        $method = $ref->getMethod('expandirBloquePlantillaPorTableIds');
        $method->setAccessible(true);

        $bloques = $method->invoke(
            null,
            1,
            $totem,
            ['totem_id' => 2, 'ubicacion_nombre' => 'Pizzería', 'detalle' => 'K1+K2'],
            $sistema,
        );

        $this->assertCount(2, $bloques);
        $this->assertSame(103443, $bloques[0]['waitry_table_id']);
        $this->assertSame(103444, $bloques[1]['waitry_table_id']);
        $this->assertSame(100.0, $bloques[0]['total_ingreso_sistema']);
        $this->assertSame(50.0, $bloques[1]['total_ingreso_sistema']);
    }

    public function test_credit_card_posnet_y_mpqr_se_distinguen_por_gateway(): void
    {
        $this->assertSame(
            WaitryMedioPagoCuentacajaSupport::TIPO_CREDIT_CARD,
            WaitryMedioPagoCuentacajaSupport::tipoRepresentativoInformeZ('credit_card'),
        );
        $this->assertSame(
            WaitryMedioPagoCuentacajaSupport::TIPO_TOTALCOIN,
            WaitryMedioPagoCuentacajaSupport::tipoRepresentativoInformeZ('credit_card', 'KIOSK MPQR'),
        );
        $this->assertSame(
            WaitryMedioPagoCuentacajaSupport::TIPO_TOTALCOIN,
            WaitryMedioPagoCuentacajaSupport::tipoRepresentativoInformeZ('totalcoin'),
        );
        $this->assertNotSame(
            WaitryMedioPagoCuentacajaSupport::tipoRepresentativoInformeZ('credit_card'),
            WaitryMedioPagoCuentacajaSupport::tipoRepresentativoInformeZ('credit_card', 'KIOSK MPQR'),
        );
    }

    public function test_conciliar_suma_totalcoin_y_credit_card_en_un_renglon(): void
    {
        $plantilla = [
            [
                'totem_id' => 10,
                'ubicacion_nombre' => 'Barra',
                'lineas' => [
                    [
                        'tipo_waitry' => 'totalcoin',
                        'etiqueta' => '226 — Totalcoin',
                        'cuentacaja_id' => 226,
                        'monto_sistema' => 80.0,
                        'cantidad_sistema' => 2,
                        'monto_informe_z' => 80.0,
                    ],
                ],
            ],
        ];

        $resultado = WaitryInformeZConciliacionSupport::conciliar($plantilla);

        $this->assertTrue($resultado['ok']);
        $this->assertCount(1, $resultado['totems'][0]['lineas']);
        $this->assertSame('totalcoin', $resultado['totems'][0]['lineas'][0]['tipo_waitry']);
    }

    public function test_conciliar_no_duplica_lineas_totem_por_cash_y_fallback(): void
    {
        $plantilla = [
            [
                'totem_id' => 4,
                'ubicacion_nombre' => 'Rebisco',
                'lineas' => [
                    [
                        'tipo_waitry' => 'mercadopago',
                        'etiqueta' => '201 — Mercado Pago',
                        'cuentacaja_id' => 201,
                        'monto_sistema' => 120.0,
                        'cantidad_sistema' => 3,
                        'monto_informe_z' => 120.0,
                    ],
                    [
                        'tipo_waitry' => 'totalcoin',
                        'etiqueta' => '226 — Totalcoin',
                        'cuentacaja_id' => 226,
                        'monto_sistema' => 45.0,
                        'cantidad_sistema' => 1,
                        'monto_informe_z' => 45.0,
                    ],
                ],
            ],
        ];

        $resultado = WaitryInformeZConciliacionSupport::conciliar($plantilla);

        $this->assertTrue($resultado['ok']);
        $this->assertCount(2, $resultado['totems'][0]['lineas']);
        $tipos = array_column($resultado['totems'][0]['lineas'], 'tipo_waitry');
        $this->assertNotContains('cash', $tipos);
        $this->assertNotContains('totem', $tipos);
    }

    public function test_plantilla_carga_unificada_por_medio_desde_total_general(): void
    {
        $resumen = [
            'por_totem' => [
                [
                    'totem_id' => 2,
                    'total_ingreso' => 447200.0,
                    'por_medio_pago' => [
                        ['categoria' => 'posnet_kiosco', 'tipo' => 'posnet_kiosco', 'etiqueta' => 'Posnet Kiosco', 'cantidad' => 10, 'total' => 189000.0],
                        ['categoria' => 'qr_kiosco', 'tipo' => 'qr_kiosco', 'etiqueta' => 'QR Kiosco', 'cantidad' => 8, 'total' => 258200.0],
                    ],
                ],
                [
                    'totem_id' => 6,
                    'total_ingreso' => 968300.0,
                    'por_medio_pago' => [
                        ['categoria' => 'posnet_kiosco', 'tipo' => 'posnet_kiosco', 'etiqueta' => 'Posnet Kiosco', 'cantidad' => 20, 'total' => 615700.0],
                        ['categoria' => 'qr_kiosco', 'tipo' => 'qr_kiosco', 'etiqueta' => 'QR Kiosco', 'cantidad' => 12, 'total' => 352600.0],
                    ],
                ],
            ],
            'total_general' => [
                'total_ingreso' => 1420900.0,
                'cantidad_ordenes' => 89,
                'por_medio_pago' => [
                    ['categoria' => 'posnet_kiosco', 'tipo' => 'posnet_kiosco', 'etiqueta' => 'Posnet Kiosco', 'cantidad' => 30, 'total' => 804700.0],
                    ['categoria' => 'qr_kiosco', 'tipo' => 'qr_kiosco', 'etiqueta' => 'QR Kiosco', 'cantidad' => 20, 'total' => 610800.0],
                    ['categoria' => 'mercadopago', 'tipo' => 'mercadopago', 'etiqueta' => 'Mercado Pago', 'cantidad' => 1, 'total' => 5400.0],
                ],
            ],
        ];

        $plantilla = WaitryInformeZConciliacionSupport::plantillaCarga(1, $resumen);

        $this->assertCount(1, $plantilla);
        $this->assertTrue($plantilla[0]['plantilla_unificada'] ?? false);
        $this->assertSame(WaitryInformeZConciliacionSupport::TOTEM_ID_PLANTILLA_UNIFICADA, $plantilla[0]['totem_id']);
        $this->assertSame(1420900.0, $plantilla[0]['total_ingreso_sistema']);

        $porEtiqueta = [];
        foreach ($plantilla[0]['lineas'] as $ln) {
            $porEtiqueta[$ln['etiqueta'] ?? ''] = (float) ($ln['monto_sistema'] ?? 0);
        }
        $this->assertSame(804700.0, $porEtiqueta['Posnet Kiosco'] ?? 0.0);
        $this->assertSame(610800.0, $porEtiqueta['QR Kiosco'] ?? 0.0);
        $this->assertSame(5400.0, $porEtiqueta['Mercado Pago'] ?? 0.0);
    }

    public function test_fusionar_informe_z_legacy_dos_totems_en_plantilla_unificada(): void
    {
        $plantilla = [
            [
                'totem_id' => WaitryInformeZConciliacionSupport::TOTEM_ID_PLANTILLA_UNIFICADA,
                'plantilla_unificada' => true,
                'lineas' => [
                    [
                        'tipo_waitry' => 'posnet_kiosco',
                        'etiqueta' => 'Posnet Kiosco',
                        'monto_sistema' => 804700.0,
                        'monto_informe_z' => null,
                    ],
                    [
                        'tipo_waitry' => 'qr_kiosco',
                        'etiqueta' => 'QR Kiosco',
                        'monto_sistema' => 610800.0,
                        'monto_informe_z' => null,
                    ],
                ],
            ],
        ];

        $informeZ = [
            'totems' => [
                [
                    'totem_id' => 2,
                    'lineas' => [
                        ['tipo_waitry' => 'posnet_kiosco', 'monto' => 800000.0],
                        ['tipo_waitry' => 'qr_kiosco', 'monto' => 250000.0],
                    ],
                ],
                [
                    'totem_id' => 6,
                    'lineas' => [
                        ['tipo_waitry' => 'posnet_kiosco', 'monto' => 4700.0],
                        ['tipo_waitry' => 'qr_kiosco', 'monto' => 360800.0],
                    ],
                ],
            ],
        ];

        $fusionada = WaitryInformeZConciliacionSupport::fusionarInformeZEnPlantilla($plantilla, $informeZ, 1);

        $this->assertSame(804700.0, $fusionada[0]['lineas'][0]['monto_informe_z']);
        $this->assertSame(610800.0, $fusionada[0]['lineas'][1]['monto_informe_z']);
    }

    public function test_precargar_montos_informe_z_desde_sistema_cuadra_conciliacion(): void
    {
        $plantilla = [
            [
                'totem_id' => 0,
                'plantilla_unificada' => true,
                'lineas' => [
                    [
                        'tipo_waitry' => 'posnet_kiosco',
                        'etiqueta' => 'Posnet Kiosco',
                        'monto_sistema' => 170100.0,
                        'monto_informe_z' => null,
                    ],
                    [
                        'tipo_waitry' => 'qr_kiosco',
                        'etiqueta' => 'QR Kiosco',
                        'monto_sistema' => 43600.0,
                        'monto_informe_z' => null,
                    ],
                ],
            ],
        ];

        $precargada = WaitryInformeZConciliacionSupport::precargarMontosInformeZDesdeSistema($plantilla);
        $resultado = WaitryInformeZConciliacionSupport::conciliar($precargada);

        $this->assertTrue($resultado['ok']);
        $this->assertSame(170100.0, $precargada[0]['lineas'][0]['monto_informe_z']);
        $this->assertSame(43600.0, $precargada[0]['lineas'][1]['monto_informe_z']);
    }

    public function test_fusionar_informe_z_plantilla_unificada_prioriza_categoria_sobre_cuenta_compartida(): void
    {
        $plantilla = [
            [
                'totem_id' => WaitryInformeZConciliacionSupport::TOTEM_ID_PLANTILLA_UNIFICADA,
                'plantilla_unificada' => true,
                'lineas' => [
                    [
                        'tipo_waitry' => WaitryMedioPagoCuentacajaSupport::CATEGORIA_POSNET_KIOSCO,
                        'cuentacaja_id' => 201,
                        'monto_sistema' => 443800.0,
                        'monto_informe_z' => null,
                    ],
                    [
                        'tipo_waitry' => WaitryMedioPagoCuentacajaSupport::CATEGORIA_QR_KIOSCO,
                        'cuentacaja_id' => 201,
                        'monto_sistema' => 968300.0,
                        'monto_informe_z' => null,
                    ],
                ],
            ],
        ];

        $informeZ = [
            'totems' => [
                [
                    'totem_id' => 0,
                    'lineas' => [
                        [
                            'tipo_waitry' => WaitryMedioPagoCuentacajaSupport::CATEGORIA_POSNET_KIOSCO,
                            'cuentacaja_id' => 201,
                            'monto_informe_z' => 443800.0,
                        ],
                        [
                            'tipo_waitry' => WaitryMedioPagoCuentacajaSupport::CATEGORIA_QR_KIOSCO,
                            'cuentacaja_id' => 201,
                            'monto_informe_z' => 968300.0,
                        ],
                    ],
                ],
            ],
        ];

        $fusionada = WaitryInformeZConciliacionSupport::fusionarInformeZEnPlantilla($plantilla, $informeZ, 1);
        $conciliacion = WaitryInformeZConciliacionSupport::conciliar($fusionada);

        $this->assertSame(443800.0, $fusionada[0]['lineas'][0]['monto_informe_z']);
        $this->assertSame(968300.0, $fusionada[0]['lineas'][1]['monto_informe_z']);
        $this->assertTrue($conciliacion['ok']);
        $this->assertSame(1412100.0, $conciliacion['totems'][0]['total_informe_z']);
    }

    public function test_reconstruir_resumen_legacy_desglosa_qr_y_posnet_en_total_general(): void
    {
        $legacy = [
            'por_totem' => [
                [
                    'totem_id' => 2,
                    'ubicacion_nombre' => 'Kiosco 1',
                    'por_medio_pago' => [
                        ['tipo' => 'totalcoin', 'cantidad' => 3, 'total' => 100.0],
                    ],
                ],
                [
                    'totem_id' => 6,
                    'ubicacion_nombre' => 'Kiosco 2',
                    'por_medio_pago' => [
                        ['tipo' => 'credit_card', 'cantidad' => 2, 'total' => 200.0],
                    ],
                ],
            ],
            'total_general' => [
                'cantidad_ordenes' => 5,
                'total_ingreso' => 300.0,
                'por_medio_pago' => [
                    ['tipo' => 'mercadopago', 'categoria' => 'mercadopago', 'etiqueta' => 'Mercado Pago', 'total' => 300.0],
                ],
            ],
        ];

        $resumen = WaitryInformeZConciliacionSupport::reconstruirResumenInformeZConDesglose($legacy);

        $porEtiqueta = [];
        foreach ($resumen['total_general']['por_medio_pago'] as $m) {
            $porEtiqueta[$m['etiqueta'] ?? ''] = (float) ($m['total'] ?? 0);
        }

        $this->assertSame(100.0, $porEtiqueta['QR Kiosco'] ?? 0.0);
        $this->assertSame(200.0, $porEtiqueta['Posnet Kiosco'] ?? 0.0);
        $this->assertArrayNotHasKey('Mercado Pago', $porEtiqueta);
        $this->assertSame(300.0, $resumen['total_general']['total_ingreso']);
    }

    public function test_resumen_sistema_desde_detalle_cierre_prefiere_persistido_sobre_snapshot(): void
    {
        $detalle = [
            'resumen_informe_z' => [
                'por_totem' => [],
                'total_general' => [
                    'cantidad_ordenes' => 71,
                    'total_ingreso' => 1834900.0,
                    'por_medio_pago' => [
                        [
                            'tipo' => WaitryMedioPagoCuentacajaSupport::CATEGORIA_POSNET_KIOSCO,
                            'categoria' => WaitryMedioPagoCuentacajaSupport::CATEGORIA_POSNET_KIOSCO,
                            'etiqueta' => 'Posnet Kiosco',
                            'cantidad' => 71,
                            'total' => 1166300.0,
                        ],
                    ],
                ],
            ],
        ];

        $resumen = WaitryInformeZConciliacionSupport::resumenSistemaDesdeDetalleCierre($detalle, 1, 50);

        $this->assertSame(1834900.0, $resumen['total_general']['total_ingreso']);
        $this->assertSame(1166300.0, $resumen['total_general']['por_medio_pago'][0]['total']);
    }
}

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
}

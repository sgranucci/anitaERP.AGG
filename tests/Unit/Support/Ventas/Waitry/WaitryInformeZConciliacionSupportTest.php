<?php

namespace Tests\Unit\Support\Ventas\Waitry;

use App\Support\Ventas\Waitry\WaitryInformeZConciliacionSupport;
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

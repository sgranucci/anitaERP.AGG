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
}

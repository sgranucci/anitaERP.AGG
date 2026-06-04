<?php

namespace Tests\Unit\Services\Ventas\Gastronomia;

use App\Services\Ventas\Gastronomia\Waitry\WaitryOrdenesExternasService;
use Tests\TestCase;

class WaitryOrdenesExternasServiceTest extends TestCase
{
    public function test_extraer_lineas_respeta_unit_price_cuando_total_no_multiplica_cantidad(): void
    {
        $svc = $this->app->make(WaitryOrdenesExternasService::class);
        $orden = [
            'cart' => [
                'items' => [
                    [
                        'external_id' => 'V0361',
                        'quantity' => 2,
                        'title' => 'Coca Cola',
                        'price' => [
                            'unit_price' => ['amount' => 3400],
                            'total_price' => ['amount' => 3400],
                        ],
                    ],
                ],
            ],
        ];

        $lineas = $svc->extraerLineasDesdeOrden($orden);

        $this->assertCount(1, $lineas);
        $this->assertSame(2.0, $lineas[0]['cantidad']);
        $this->assertSame(3400.0, $lineas[0]['precio_unitario']);
    }

    public function test_extraer_lineas_usa_total_con_modificadores_en_cantidad_uno(): void
    {
        $svc = $this->app->make(WaitryOrdenesExternasService::class);
        $orden = [
            'cart' => [
                'items' => [
                    [
                        'external_id' => 'V0277',
                        'quantity' => 1,
                        'title' => 'Tostado',
                        'price' => [
                            'unit_price' => ['amount' => 8400],
                            'total_price' => ['amount' => 11800],
                        ],
                    ],
                ],
            ],
        ];

        $lineas = $svc->extraerLineasDesdeOrden($orden);

        $this->assertCount(1, $lineas);
        $this->assertSame(11800.0, $lineas[0]['precio_unitario']);
    }

    public function test_extraer_lineas_divide_total_cuando_refleja_cantidad(): void
    {
        $svc = $this->app->make(WaitryOrdenesExternasService::class);
        $orden = [
            'cart' => [
                'items' => [
                    [
                        'external_id' => 'V0361',
                        'quantity' => 2,
                        'title' => 'Coca Cola',
                        'price' => [
                            'unit_price' => ['amount' => 3400],
                            'total_price' => ['amount' => 6800],
                        ],
                    ],
                ],
            ],
        ];

        $lineas = $svc->extraerLineasDesdeOrden($orden);

        $this->assertSame(3400.0, $lineas[0]['precio_unitario']);
    }
}

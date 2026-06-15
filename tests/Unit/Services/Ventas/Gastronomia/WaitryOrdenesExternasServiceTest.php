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

    public function test_extraer_lineas_incluye_items_pagados_en_order_items(): void
    {
        $svc = $this->app->make(WaitryOrdenesExternasService::class);
        $orden = [
            'orderId' => 17576277,
            'paid' => true,
            'orderItems' => [
                [
                    'count' => 1,
                    'price' => 4500.0,
                    'paid' => true,
                    'item' => [
                        'name' => 'Café con leche',
                        'price' => 4500.0,
                        'externalId' => 'V0123',
                    ],
                ],
            ],
        ];

        $this->assertSame([], $svc->extraerLineasDesdeOrden($orden));

        $lineas = $svc->extraerLineasDesdeOrden($orden, true);

        $this->assertCount(1, $lineas);
        $this->assertSame('V0123', $lineas[0]['sku']);
        $this->assertSame(4500.0, $lineas[0]['precio_unitario']);
    }

    /** Regresión POS: getOrdersPOS usa cart.items; no aplica filtro paid de orderItems. */
    public function test_extraer_lineas_pos_get_orders_pos_cart_items_sin_cambios(): void
    {
        $svc = $this->app->make(WaitryOrdenesExternasService::class);
        $orden = [
            'id' => 99901,
            'paid' => false,
            'cart' => [
                'items' => [
                    [
                        'external_id' => 'V0456',
                        'quantity' => 1,
                        'title' => 'Medialuna',
                        'price' => ['unit_price' => ['amount' => 1200], 'total_price' => ['amount' => 1200]],
                    ],
                ],
            ],
            'orderItems' => [
                [
                    'count' => 1,
                    'price' => 9999.0,
                    'paid' => true,
                    'item' => ['name' => 'No debe usarse', 'externalId' => 'V9999'],
                ],
            ],
        ];

        $lineas = $svc->extraerLineasDesdeOrden($orden);

        $this->assertCount(1, $lineas);
        $this->assertSame('V0456', $lineas[0]['sku']);
        $this->assertSame(1200.0, $lineas[0]['precio_unitario']);
    }

    /** Variación con SKU cuando el ítem padre no trae externalId (ej. Cerveza Goyeneche → Goyeneche Blonde V0942). */
    public function test_extraer_lineas_usa_sku_de_variacion_cuando_item_padre_sin_external_id(): void
    {
        $svc = $this->app->make(WaitryOrdenesExternasService::class);
        $orden = [
            'orderId' => 17590960,
            'paid' => true,
            'orderItems' => [
                [
                    'count' => 2,
                    'price' => 5400.0,
                    'paid' => true,
                    'item' => [
                        'itemId' => 1440341,
                        'name' => 'Cerveza Goyeneche',
                        'price' => 5400.0,
                        'externalId' => null,
                    ],
                    'orderItemVariations' => [
                        [
                            'itemVariation' => [
                                'itemVariationId' => 937435,
                                'item' => [
                                    'itemId' => 1432894,
                                    'name' => 'Goyeneche Blonde',
                                    'externalId' => 'V0942',
                                    'externalCode' => 'V0942',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $lineas = $svc->extraerLineasDesdeOrden($orden, true);

        $this->assertCount(1, $lineas);
        $this->assertSame('V0942', $lineas[0]['sku']);
        $this->assertSame('Goyeneche Blonde', $lineas[0]['titulo']);
        $this->assertSame(2.0, $lineas[0]['cantidad']);
        $this->assertSame(5400.0, $lineas[0]['precio_unitario']);
    }

    /**
     * Regresión cierre jornada Kandiko 13/06/2026: getordersdetails trae externalCode numérico
     * en el ítem padre (1440341) y el SKU Anita en la variación (V0942).
     */
    public function test_extraer_lineas_ignora_external_code_numerico_waitry_y_usa_variacion(): void
    {
        $svc = $this->app->make(WaitryOrdenesExternasService::class);
        $orden = [
            'orderId' => 17707446,
            'paid' => true,
            'orderItems' => [
                [
                    'count' => 2,
                    'price' => 5400.0,
                    'paid' => true,
                    'item' => [
                        'itemId' => 1456141,
                        'name' => 'Cerveza Goyeneche',
                        'price' => 5400.0,
                        'externalId' => null,
                        'externalCode' => '1440341',
                    ],
                    'orderItemVariations' => [
                        [
                            'itemVariation' => [
                                'itemVariationId' => 943256,
                                'externalCode' => '937435',
                                'item' => [
                                    'itemId' => 1456157,
                                    'name' => 'Goyeneche Blonde',
                                    'externalId' => 'V0942',
                                    'externalCode' => 'V0942',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $lineas = $svc->extraerLineasDesdeOrden($orden, true);

        $this->assertCount(1, $lineas);
        $this->assertSame('V0942', $lineas[0]['sku']);
        $this->assertSame('Goyeneche Blonde', $lineas[0]['titulo']);
        $this->assertSame(2.0, $lineas[0]['cantidad']);
    }

    /** Regresión POS: importación sigue omitiendo ítems ya pagados en orderItems (default). */
    public function test_extraer_lineas_pos_omite_items_pagados_en_order_items_por_defecto(): void
    {
        $svc = $this->app->make(WaitryOrdenesExternasService::class);
        $orden = [
            'orderId' => 88801,
            'orderItems' => [
                [
                    'count' => 1,
                    'price' => 3000.0,
                    'paid' => true,
                    'item' => ['name' => 'Tostado', 'externalId' => 'V0277'],
                ],
                [
                    'count' => 1,
                    'price' => 1500.0,
                    'paid' => false,
                    'item' => ['name' => 'Café', 'externalId' => 'V0288'],
                ],
            ],
        ];

        $lineas = $svc->extraerLineasDesdeOrden($orden);

        $this->assertCount(1, $lineas);
        $this->assertSame('V0288', $lineas[0]['sku']);
    }
}

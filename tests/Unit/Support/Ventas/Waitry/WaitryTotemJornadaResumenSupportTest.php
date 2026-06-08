<?php

namespace Tests\Unit\Support\Ventas\Waitry;

use App\Models\Ventas\TotemWaitryGastronomia;
use App\Support\Ventas\Waitry\WaitryTotemJornadaResumenSupport;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class WaitryTotemJornadaResumenSupportTest extends TestCase
{
    public function test_consolidar_ingreso_huerfano_en_unico_totem(): void
    {
        $totem = new TotemWaitryGastronomia;
        $totem->id = 7;
        $totem->waitry_table_id = 100;
        $totem->detalle = 'Principal';

        $resumen = [
            'por_totem' => [
                [
                    'totem_id' => null,
                    'ubicacion_nombre' => 'Sin tótem asignado',
                    'total_ingreso' => 150.0,
                    'cantidad_ordenes' => 2,
                    'por_medio_pago' => [
                        [
                            'tipo' => 'mercadopago',
                            'etiqueta' => 'MP',
                            'cantidad' => 2,
                            'total' => 150.0,
                        ],
                    ],
                ],
            ],
            'total_general' => [
                'cantidad_ordenes' => 2,
                'total_ingreso' => 150.0,
                'por_medio_pago' => [],
            ],
        ];

        $out = WaitryTotemJornadaResumenSupport::consolidarIngresoEnUnicoTotemSiAplica(
            $resumen,
            new Collection([$totem]),
        );

        $this->assertCount(1, $out['por_totem']);
        $this->assertSame(7, $out['por_totem'][0]['totem_id']);
        $this->assertSame(150.0, $out['por_totem'][0]['total_ingreso']);
        $this->assertSame(150.0, $out['por_totem'][0]['por_medio_pago'][0]['total']);
    }

    public function test_armar_suma_cobro_sin_tipo_pago_waitry_en_medio_configurado(): void
    {
        config(['waitry.tipo_pago_cuentacaja' => ['mercadopago' => 101]]);

        $totem = new TotemWaitryGastronomia;
        $totem->id = 3;
        $totem->empresa_id = 1;
        $totem->waitry_table_id = 50;
        $totem->detalle = 'Barra';

        $resumen = WaitryTotemJornadaResumenSupport::armar(
            new Collection([$totem]),
            [
                [
                    'waitry_tipo_pago' => null,
                    'paid_waitry' => true,
                    'monto_cobro_waitry' => 200.0,
                    'total' => 200.0,
                    'waitry_table_id' => 50,
                ],
            ],
            1,
        );

        $this->assertSame(200.0, $resumen['total_general']['total_ingreso']);
        $this->assertSame(1, $resumen['total_general']['cantidad_ordenes']);
        $this->assertGreaterThan(0.0, $resumen['por_totem'][0]['por_medio_pago'][0]['total'] ?? 0);
    }

    public function test_armar_reparte_por_order_id_cuando_dos_totems_sin_waitry_table_id(): void
    {
        config(['waitry.tipo_pago_cuentacaja' => ['totalcoin' => 102]]);

        $t1 = new TotemWaitryGastronomia;
        $t1->id = 1;
        $t1->empresa_id = 1;
        $t1->waitry_table_id = null;

        $t2 = new TotemWaitryGastronomia;
        $t2->id = 2;
        $t2->empresa_id = 1;
        $t2->waitry_table_id = null;

        $resumen = WaitryTotemJornadaResumenSupport::armar(
            new Collection([$t1, $t2]),
            [
                [
                    'waitry_order_id' => 10,
                    'waitry_tipo_pago' => 'totalcoin',
                    'paid_waitry' => true,
                    'monto_cobro_waitry' => 100.0,
                    'total' => 100.0,
                    'waitry_table_id' => 101066,
                ],
                [
                    'waitry_order_id' => 11,
                    'waitry_tipo_pago' => 'totalcoin',
                    'paid_waitry' => true,
                    'monto_cobro_waitry' => 50.0,
                    'total' => 50.0,
                    'waitry_table_id' => 101066,
                ],
            ],
            1,
        );

        $porId = [];
        foreach ($resumen['por_totem'] as $bloque) {
            $porId[(int) ($bloque['totem_id'] ?? 0)] = (float) ($bloque['total_ingreso'] ?? 0);
        }

        $this->assertArrayHasKey(1, $porId);
        $this->assertArrayHasKey(2, $porId);
        $this->assertSame(150.0, round($porId[1] + $porId[2], 2));
        $this->assertNotContains(0, array_keys($porId));
    }

    public function test_armar_para_informe_z_dos_totems_k1_k2(): void
    {
        $k1 = new TotemWaitryGastronomia;
        $k1->id = 2;
        $k1->empresa_id = 1;
        $k1->waitry_layout_id = 32392;
        $k1->waitry_table_id = 103443;
        $k1->informe_z_habilitado = true;

        $k2 = new TotemWaitryGastronomia;
        $k2->id = 3;
        $k2->empresa_id = 1;
        $k2->waitry_layout_id = 32393;
        $k2->waitry_table_id = 103444;
        $k2->informe_z_habilitado = true;

        $resumen = WaitryTotemJornadaResumenSupport::armarParaInformeZ(
            new Collection([$k1, $k2]),
            [
                [
                    'waitry_tipo_pago' => 'creditcard',
                    'paid_waitry' => true,
                    'monto_cobro_waitry' => 100.0,
                    'total' => 100.0,
                    'waitry_layout_id' => 32392,
                    'waitry_table_id' => 103443,
                ],
                [
                    'waitry_tipo_pago' => 'creditcard',
                    'paid_waitry' => true,
                    'monto_cobro_waitry' => 50.0,
                    'total' => 50.0,
                    'waitry_layout_id' => 32393,
                    'waitry_table_id' => 103444,
                ],
            ],
            1,
        );

        $this->assertCount(2, $resumen['por_totem']);
        $porId = [];
        foreach ($resumen['por_totem'] as $bloque) {
            $porId[(int) $bloque['totem_id']] = (float) $bloque['total_ingreso'];
        }
        $this->assertSame(100.0, $porId[2] ?? 0.0);
        $this->assertSame(50.0, $porId[3] ?? 0.0);
        $this->assertSame(150.0, $resumen['total_general']['total_ingreso']);
    }

    public function test_armar_matchea_por_layout_id_sin_table_id_en_totem(): void
    {
        config(['waitry.tipo_pago_cuentacaja' => ['mercadopago' => 101]]);

        $totem = new TotemWaitryGastronomia;
        $totem->id = 5;
        $totem->empresa_id = 1;
        $totem->waitry_layout_id = 32392;
        $totem->waitry_table_id = null;
        $totem->detalle = 'Kiosco 1';

        $resumen = WaitryTotemJornadaResumenSupport::armar(
            new Collection([$totem]),
            [
                [
                    'waitry_tipo_pago' => 'mercadopago',
                    'paid_waitry' => true,
                    'monto_cobro_waitry' => 80.0,
                    'total' => 80.0,
                    'waitry_layout_id' => 32392,
                    'waitry_table_id' => 103443,
                ],
            ],
            1,
        );

        $this->assertCount(1, $resumen['por_totem']);
        $this->assertSame(5, $resumen['por_totem'][0]['totem_id']);
        $this->assertSame(80.0, $resumen['por_totem'][0]['total_ingreso']);
    }

    public function test_armar_layout_id_tiene_prioridad_sobre_table_id(): void
    {
        config(['waitry.tipo_pago_cuentacaja' => ['mercadopago' => 101]]);

        $kiosco1 = new TotemWaitryGastronomia;
        $kiosco1->id = 10;
        $kiosco1->empresa_id = 1;
        $kiosco1->waitry_layout_id = 32392;
        $kiosco1->detalle = 'Kiosco 1';

        $kiosco2 = new TotemWaitryGastronomia;
        $kiosco2->id = 11;
        $kiosco2->empresa_id = 1;
        $kiosco2->waitry_layout_id = 32393;
        $kiosco2->waitry_table_id = 103443;

        $resumen = WaitryTotemJornadaResumenSupport::armar(
            new Collection([$kiosco1, $kiosco2]),
            [
                [
                    'waitry_tipo_pago' => 'mercadopago',
                    'paid_waitry' => true,
                    'monto_cobro_waitry' => 50.0,
                    'total' => 50.0,
                    'waitry_layout_id' => 32392,
                    'waitry_table_id' => 103443,
                ],
            ],
            1,
        );

        $porId = [];
        foreach ($resumen['por_totem'] as $bloque) {
            $porId[(int) $bloque['totem_id']] = (float) $bloque['total_ingreso'];
        }

        $this->assertSame(50.0, $porId[10] ?? 0.0);
        $this->assertSame(0.0, $porId[11] ?? -1.0);
    }

    public function test_armar_matchea_por_layout_adicional_en_mismo_totem(): void
    {
        config(['waitry.tipo_pago_cuentacaja' => ['mercadopago' => 101]]);

        $totem = new TotemWaitryGastronomia;
        $totem->id = 2;
        $totem->empresa_id = 1;
        $totem->waitry_layout_id = 32392;
        $totem->waitry_layout_ids_adicionales = '32393';
        $totem->waitry_table_id = 103443;
        $totem->waitry_table_ids_adicionales = '103444';

        $resumen = WaitryTotemJornadaResumenSupport::armar(
            new Collection([$totem]),
            [
                [
                    'waitry_tipo_pago' => 'mercadopago',
                    'paid_waitry' => true,
                    'monto_cobro_waitry' => 70.0,
                    'total' => 70.0,
                    'waitry_layout_id' => 32393,
                    'waitry_table_id' => 103444,
                ],
            ],
            1,
        );

        $this->assertCount(1, $resumen['por_totem']);
        $this->assertSame(2, $resumen['por_totem'][0]['totem_id']);
        $this->assertSame(70.0, $resumen['por_totem'][0]['total_ingreso']);
    }

    public function test_linea_cuenta_ingreso_totem_con_monto_waitry_sin_flag_erp(): void
    {
        $linea = [
            'waitry_tipo_pago' => 'mercadopago',
            'paid_waitry' => null,
            'waitry_cobro_totem' => false,
            'monto_cobro_waitry' => 125.50,
            'total' => 125.50,
        ];

        $this->assertTrue(WaitryTotemJornadaResumenSupport::lineaCuentaParaIngresoTotem($linea));
    }
}

<?php

namespace Tests\Unit\Support\Ventas\Waitry;

use App\Models\Ventas\TotemWaitryGastronomia;
use App\Support\Ventas\Waitry\WaitryMedioPagoCuentacajaSupport;
use App\Support\Ventas\Waitry\WaitryTotemJornadaResumenSupport;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class WaitryInformeZSistemaResumenTest extends TestCase
{
    public function test_armar_para_informe_z_suma_qr_mp_posnet_sin_facturar(): void
    {
        config(['waitry.tipo_pago_cuentacaja' => [
            'mercadopago' => 201,
            'totalcoin' => 201,
        ]]);

        $totem = new TotemWaitryGastronomia;
        $totem->id = 5;
        $totem->empresa_id = 1;
        $totem->waitry_table_id = 99;

        $resumen = WaitryTotemJornadaResumenSupport::armarParaInformeZ(
            new Collection([$totem]),
            [
                [
                    'waitry_tipo_pago' => 'credit_card',
                    'waitry_payment_gateway' => 'KIOSK MP',
                    'paid_waitry' => true,
                    'monto_cobro_waitry' => 200.0,
                    'waitry_table_id' => 99,
                ],
                [
                    'waitry_tipo_pago' => 'cash',
                    'paid_waitry' => true,
                    'monto_cobro_waitry' => 50.0,
                    'waitry_table_id' => 99,
                ],
                [
                    'waitry_tipo_pago' => 'mercadopago',
                    'paid_waitry' => true,
                    'monto_cobro_waitry' => 80.0,
                    'waitry_table_id' => 99,
                ],
                [
                    'waitry_tipo_pago' => 'totalcoin',
                    'waitry_payment_gateway' => 'KIOSK MPQR',
                    'paid_waitry' => true,
                    'monto_cobro_waitry' => 150.0,
                    'waitry_table_id' => 99,
                ],
            ],
            1,
        );

        $this->assertSame(430.0, $resumen['total_general']['total_ingreso']);
        $this->assertSame(3, $resumen['total_general']['cantidad_ordenes']);
        $this->assertCount(3, $resumen['total_general']['por_medio_pago']);
        $totalesPorEtiqueta = [];
        foreach ($resumen['total_general']['por_medio_pago'] as $medio) {
            $totalesPorEtiqueta[$medio['etiqueta'] ?? ''] = (float) ($medio['total'] ?? 0);
        }
        $this->assertSame(200.0, $totalesPorEtiqueta['Posnet Kiosco'] ?? 0.0);
        $this->assertSame(150.0, $totalesPorEtiqueta['QR Kiosco'] ?? 0.0);
        $this->assertSame(80.0, $totalesPorEtiqueta['Mercado Pago'] ?? 0.0);
    }

    public function test_armar_para_informe_z_desglosa_qr_y_posnet_misma_cuentacaja(): void
    {
        config(['waitry.tipo_pago_cuentacaja' => ['mercadopago' => 201, 'totalcoin' => 201]]);

        $totem = new TotemWaitryGastronomia;
        $totem->id = 2;
        $totem->empresa_id = 1;
        $totem->waitry_table_id = 103443;

        $resumen = WaitryTotemJornadaResumenSupport::armarParaInformeZ(
            new Collection([$totem]),
            [
                [
                    'waitry_tipo_pago' => 'kioskmp',
                    'waitry_payment_gateway' => 'KIOSK MP',
                    'paid_waitry' => true,
                    'monto_cobro_waitry' => 6600.0,
                    'waitry_table_id' => 103443,
                ],
                [
                    'waitry_tipo_pago' => 'kioskmpqr',
                    'waitry_payment_gateway' => 'KIOSK MPQR',
                    'paid_waitry' => true,
                    'monto_cobro_waitry' => 9800.0,
                    'waitry_table_id' => 103443,
                ],
            ],
            1,
        );

        $this->assertSame(16400.0, $resumen['total_general']['total_ingreso']);
        $this->assertCount(2, $resumen['por_totem'][0]['por_medio_pago']);
        $porEtiqueta = [];
        foreach ($resumen['por_totem'][0]['por_medio_pago'] as $m) {
            $porEtiqueta[$m['etiqueta']] = (float) $m['total'];
        }
        $this->assertSame(6600.0, $porEtiqueta['Posnet Kiosco'] ?? 0.0);
        $this->assertSame(9800.0, $porEtiqueta['QR Kiosco'] ?? 0.0);
    }

    public function test_facturada_anita_sin_cobro_totem_no_suma_en_informe_z(): void
    {
        config(['waitry.tipo_pago_cuentacaja' => ['totalcoin' => 102]]);
        config(['gastronomia.cuentacaja_efectivo_por_empresa' => [1 => 55]]);

        $totem = new TotemWaitryGastronomia;
        $totem->id = 5;
        $totem->empresa_id = 1;
        $totem->waitry_table_id = 99;

        $resumen = WaitryTotemJornadaResumenSupport::armarParaInformeZ(
            new Collection([$totem]),
            [
                [
                    'importada_erp' => true,
                    'facturada_erp' => true,
                    'waitry_cobro_totem' => false,
                    'waitry_tipo_pago' => 'credit_card',
                    'anita_cuentacaja_id' => 55,
                    'paid_waitry' => true,
                    'monto_cobro_waitry' => 120.0,
                    'waitry_table_id' => 99,
                ],
            ],
            1,
        );

        $this->assertSame(0.0, $resumen['total_general']['total_ingreso']);
        $this->assertFalse(WaitryTotemJornadaResumenSupport::lineaEntraInformeZSistema([
            'importada_erp' => true,
            'facturada_erp' => true,
            'waitry_cobro_totem' => false,
            'waitry_tipo_pago' => 'credit_card',
            'anita_cuentacaja_id' => 55,
            'paid_waitry' => true,
        ]));
    }

    public function test_facturada_con_cobro_totem_si_suma_en_informe_z(): void
    {
        $totem = new TotemWaitryGastronomia;
        $totem->id = 5;
        $totem->empresa_id = 1;
        $totem->waitry_table_id = 99;

        $resumen = WaitryTotemJornadaResumenSupport::armarParaInformeZ(
            new Collection([$totem]),
            [
                [
                    'importada_erp' => true,
                    'facturada_erp' => true,
                    'waitry_cobro_totem' => true,
                    'waitry_tipo_pago' => 'credit_card',
                    'paid_waitry' => true,
                    'monto_cobro_waitry' => 90.0,
                    'waitry_table_id' => 99,
                ],
            ],
            1,
        );

        $this->assertSame(90.0, $resumen['total_general']['total_ingreso']);
    }

    public function test_facturada_con_anita_es_totem_suma_qr(): void
    {
        config(['waitry.tipo_pago_cuentacaja' => ['totalcoin' => 201, 'mercadopago' => 201]]);

        $totem = new TotemWaitryGastronomia;
        $totem->id = 5;
        $totem->empresa_id = 1;
        $totem->waitry_table_id = 99;

        $resumen = WaitryTotemJornadaResumenSupport::armarParaInformeZ(
            new Collection([$totem]),
            [
                [
                    'importada_erp' => true,
                    'facturada_erp' => true,
                    'anita_es_totem' => true,
                    'waitry_tipo_pago' => 'totalcoin',
                    'waitry_payment_gateway' => 'KIOSK MPQR',
                    'paid_waitry' => true,
                    'monto_cobro_waitry' => 75.0,
                    'waitry_table_id' => 99,
                ],
            ],
            1,
        );

        $this->assertSame(75.0, $resumen['total_general']['total_ingreso']);
    }

    public function test_solo_waitry_sin_importar_suma_en_informe_z(): void
    {
        $totem = new TotemWaitryGastronomia;
        $totem->id = 5;
        $totem->waitry_table_id = 99;

        $this->assertTrue(WaitryTotemJornadaResumenSupport::lineaEntraInformeZSistema([
            'importada_erp' => false,
            'facturada_erp' => false,
            'waitry_tipo_pago' => 'credit_card',
            'paid_waitry' => true,
        ]));
    }

    public function test_armar_para_informe_z_incluye_credit_card_mpqr(): void
    {
        config(['waitry.tipo_pago_cuentacaja' => ['totalcoin' => 201]]);

        $totem = new TotemWaitryGastronomia;
        $totem->id = 5;
        $totem->empresa_id = 1;
        $totem->waitry_table_id = 99;

        $resumen = WaitryTotemJornadaResumenSupport::armarParaInformeZ(
            new Collection([$totem]),
            [
                [
                    'waitry_tipo_pago' => 'credit_card',
                    'waitry_payment_gateway' => 'KIOSK MPQR',
                    'paid_waitry' => true,
                    'monto_cobro_waitry' => 200.0,
                    'waitry_table_id' => 99,
                ],
            ],
            1,
        );

        $this->assertSame(200.0, $resumen['total_general']['total_ingreso']);
    }

    public function test_armar_para_informe_z_incluye_interface_qr_celular(): void
    {
        config(['waitry.tipo_pago_cuentacaja' => ['totalcoin' => 201, 'mercadopago' => 201]]);

        $totem = new TotemWaitryGastronomia;
        $totem->id = 5;
        $totem->empresa_id = 1;
        $totem->waitry_table_id = 99;

        $resumen = WaitryTotemJornadaResumenSupport::armarParaInformeZ(
            new Collection([$totem]),
            [
                [
                    'waitry_tipo_pago' => 'interface',
                    'waitry_payment_gateway' => 'TOTALCOIN',
                    'paid_waitry' => true,
                    'monto_cobro_waitry' => 65.0,
                    'waitry_table_id' => 99,
                    'empresa_id' => 1,
                ],
            ],
            1,
        );

        $this->assertSame(65.0, $resumen['total_general']['total_ingreso']);
    }

    public function test_linea_entra_informe_z_sistema_acepta_qr_mp_posnet(): void
    {
        $this->assertTrue(WaitryMedioPagoCuentacajaSupport::lineaEntraInformeZSistema([
            'waitry_tipo_pago' => 'credit_card',
        ]));
        $this->assertTrue(WaitryMedioPagoCuentacajaSupport::lineaEntraInformeZSistema([
            'waitry_tipo_pago' => 'credit_card',
            'waitry_payment_gateway' => 'KIOSK MPQR',
        ]));
        $this->assertTrue(WaitryMedioPagoCuentacajaSupport::lineaEntraInformeZSistema([
            'waitry_tipo_pago' => 'totalcoin',
        ]));
        $this->assertTrue(WaitryMedioPagoCuentacajaSupport::lineaEntraInformeZSistema([
            'waitry_tipo_pago' => 'mercadopago',
        ]));
        $this->assertFalse(WaitryMedioPagoCuentacajaSupport::lineaEntraInformeZSistema([
            'waitry_tipo_pago' => 'cash',
        ]));
        $this->assertFalse(WaitryMedioPagoCuentacajaSupport::lineaEntraInformeZSistema([
            'waitry_tipo_pago' => 'credit_card',
            'display_id' => 'E-ABC123',
        ]));
        $this->assertTrue(WaitryMedioPagoCuentacajaSupport::lineaEntraInformeZSistema([
            'waitry_tipo_pago' => 'interface',
            'waitry_payment_gateway' => 'TOTALCOIN',
        ]));
        $this->assertFalse(WaitryMedioPagoCuentacajaSupport::lineaEntraInformeZSistema([
            'waitry_tipo_pago' => 'interface',
            'waitry_payment_gateway' => 'ANITA',
            'facturada_erp' => true,
        ]));
    }
}

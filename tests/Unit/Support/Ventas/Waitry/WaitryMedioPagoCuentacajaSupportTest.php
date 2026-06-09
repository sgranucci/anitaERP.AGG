<?php

namespace Tests\Unit\Support\Ventas\Waitry;

use App\Support\Ventas\Gastronomia\CierreJornadaProcesoMedioSupport;
use App\Support\Ventas\Waitry\WaitryMedioPagoCuentacajaSupport;
use Tests\TestCase;

final class WaitryMedioPagoCuentacajaSupportTest extends TestCase
{
    public function test_es_tipo_predefinido_solo_mapeos_configurados(): void
    {
        $this->assertTrue(WaitryMedioPagoCuentacajaSupport::esTipoPredefinido('mercadopago'));
        $this->assertTrue(WaitryMedioPagoCuentacajaSupport::esTipoPredefinido('Mercado-Pago'));
        $this->assertTrue(WaitryMedioPagoCuentacajaSupport::esTipoPredefinido('totalcoin'));
        $this->assertFalse(WaitryMedioPagoCuentacajaSupport::esTipoPredefinido('cash'));
        $this->assertFalse(WaitryMedioPagoCuentacajaSupport::esTipoPredefinido('credit_card'));
    }

    public function test_es_tipo_excluido_informe_z_solo_totem_puente(): void
    {
        $this->assertFalse(WaitryMedioPagoCuentacajaSupport::esTipoExcluidoInformeZ('cash'));
        $this->assertTrue(WaitryMedioPagoCuentacajaSupport::esTipoExcluidoInformeZ('totem'));
        $this->assertFalse(WaitryMedioPagoCuentacajaSupport::esTipoExcluidoInformeZ(null));
        $this->assertFalse(WaitryMedioPagoCuentacajaSupport::esTipoExcluidoInformeZ('mercadopago'));
    }

    public function test_facturada_anita_efectivo_no_usa_credit_card_waitry_en_informe_z(): void
    {
        config(['gastronomia.cuentacaja_efectivo_por_empresa' => [1 => 55]]);

        $tipo = WaitryMedioPagoCuentacajaSupport::resolverTipoMedioInformeZDesdeLinea([
            'facturada_erp' => true,
            'waitry_tipo_pago' => 'creditcard',
            'anita_cuentacaja_id' => 55,
            'anita_es_totem' => false,
        ], 1);

        $this->assertSame('cash', $tipo);
        $this->assertSame('cash', WaitryMedioPagoCuentacajaSupport::tipoRepresentativoInformeZ('cash'));
    }

    public function test_waitry_cash_sin_facturar_no_entra_informe_z(): void
    {
        $tipo = WaitryMedioPagoCuentacajaSupport::resolverTipoMedioInformeZDesdeLinea([
            'facturada_erp' => false,
            'waitry_tipo_pago' => 'cash',
            'paid_waitry' => true,
            'monto_cobro_waitry' => 100,
        ], 1);

        $this->assertNull($tipo);
    }

    public function test_tipo_totem_es_cuenta_puente_no_medio_informe_z(): void
    {
        $this->assertTrue(WaitryMedioPagoCuentacajaSupport::esTipoCuentaPuenteFacturacion('totem'));
        $this->assertTrue(WaitryMedioPagoCuentacajaSupport::esTipoExcluidoInformeZ('totem'));
        $this->assertNull(WaitryMedioPagoCuentacajaSupport::tipoRepresentativoInformeZ('totem'));
    }

    public function test_resolver_tipo_medio_sin_payment_type_usa_fallback_configurado(): void
    {
        config(['waitry.tipo_pago_cuentacaja' => [
            'mercadopago' => 101,
            'totalcoin' => 102,
        ]]);

        $tipo = WaitryMedioPagoCuentacajaSupport::resolverTipoMedioInformeZDesdeLinea([
            'waitry_tipo_pago' => null,
            'monto_cobro_waitry' => 50.0,
        ], 1);

        $this->assertNotNull($tipo);
        $this->assertNotSame('totem', $tipo);
        $this->assertNotSame('cash', $tipo);
    }

    public function test_es_tipo_qr_waitry_totalcoin_y_credit_card_mpqr(): void
    {
        $this->assertTrue(WaitryMedioPagoCuentacajaSupport::esTipoQrWaitry('totalcoin'));
        $this->assertTrue(WaitryMedioPagoCuentacajaSupport::esTipoQrWaitry('credit_card', 'KIOSK MPQR'));
        $this->assertTrue(WaitryMedioPagoCuentacajaSupport::esTipoQrWaitry('kioskmpqr', 'KIOSK MPQR'));
        $this->assertFalse(WaitryMedioPagoCuentacajaSupport::esTipoQrWaitry('credit_card'));
        $this->assertFalse(WaitryMedioPagoCuentacajaSupport::esTipoQrWaitry('credit_card', 'KIOSK MP'));
        $this->assertFalse(WaitryMedioPagoCuentacajaSupport::esTipoQrWaitry('kioskmp', 'KIOSK MP'));
        $this->assertFalse(WaitryMedioPagoCuentacajaSupport::esTipoQrWaitry('mercadopago'));
        $this->assertFalse(WaitryMedioPagoCuentacajaSupport::esTipoQrWaitry('cash'));
    }

    public function test_kioskmp_es_posnet_y_redistribuible(): void
    {
        $this->assertTrue(WaitryMedioPagoCuentacajaSupport::esCreditCardPosnet('kioskmp', 'KIOSK MP'));
        $this->assertSame(
            CierreJornadaProcesoMedioSupport::CLAVE_MP,
            CierreJornadaProcesoMedioSupport::claveDesdeWaitryTipo('kioskmp', 'KIOSK MP'),
        );
        $this->assertTrue(CierreJornadaProcesoMedioSupport::esWaitrySinFacturarRedistribuible('kioskmp', 'KIOSK MP'));
    }

    public function test_kioskmpqr_es_qr_y_redistribuible(): void
    {
        $this->assertTrue(WaitryMedioPagoCuentacajaSupport::esTipoQrWaitry('kioskmpqr', 'KIOSK MPQR'));
        $this->assertSame(
            CierreJornadaProcesoMedioSupport::CLAVE_QR,
            CierreJornadaProcesoMedioSupport::claveDesdeWaitryTipo('kioskmpqr', 'KIOSK MPQR'),
        );
        $this->assertTrue(CierreJornadaProcesoMedioSupport::esWaitrySinFacturarRedistribuible('kioskmpqr', 'KIOSK MPQR'));
    }

    public function test_linea_entra_informe_z_acepta_medios_kiosco_y_excluye_push_erp(): void
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
        $this->assertFalse(WaitryMedioPagoCuentacajaSupport::lineaEntraInformeZSistema([
            'waitry_tipo_pago' => 'credit_card',
            'display_id' => 'E-ABC123',
        ]));
    }

    public function test_etiqueta_tipo_qr_para_credit_card_mpqr(): void
    {
        $this->assertSame('QR MP (kiosco)', WaitryMedioPagoCuentacajaSupport::etiquetaTipo('credit_card', 'KIOSK MPQR'));
        $this->assertSame('Posnet (tótem)', WaitryMedioPagoCuentacajaSupport::etiquetaTipo('credit_card'));
    }

    public function test_extraer_tipo_pago_normaliza_credit_card_con_gateway_interface(): void
    {
        $orden = [
            'payment' => [
                'type' => 'credit_card',
                'payments' => [['gateway' => 'interface', 'amount' => 9700]],
            ],
        ];

        $this->assertSame('interface', WaitryMedioPagoCuentacajaSupport::extraerTipoPagoOrden($orden));
    }
}

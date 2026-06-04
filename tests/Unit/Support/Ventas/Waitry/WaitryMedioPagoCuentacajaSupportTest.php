<?php

namespace Tests\Unit\Support\Ventas\Waitry;

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

    public function test_es_tipo_excluido_informe_z_cash_totem_y_null(): void
    {
        $this->assertTrue(WaitryMedioPagoCuentacajaSupport::esTipoExcluidoInformeZ('cash'));
        $this->assertTrue(WaitryMedioPagoCuentacajaSupport::esTipoExcluidoInformeZ('CASH'));
        $this->assertTrue(WaitryMedioPagoCuentacajaSupport::esTipoExcluidoInformeZ('totem'));
        $this->assertTrue(WaitryMedioPagoCuentacajaSupport::esTipoExcluidoInformeZ(null));
        $this->assertFalse(WaitryMedioPagoCuentacajaSupport::esTipoExcluidoInformeZ('mercadopago'));
        $this->assertFalse(WaitryMedioPagoCuentacajaSupport::esTipoExcluidoInformeZ('debit_card'));
    }

    public function test_es_tipo_qr_waitry_totalcoin_y_credit_card(): void
    {
        $this->assertTrue(WaitryMedioPagoCuentacajaSupport::esTipoQrWaitry('totalcoin'));
        $this->assertTrue(WaitryMedioPagoCuentacajaSupport::esTipoQrWaitry('credit_card'));
        $this->assertTrue(WaitryMedioPagoCuentacajaSupport::esTipoQrWaitry('CREDIT-CARD'));
        $this->assertFalse(WaitryMedioPagoCuentacajaSupport::esTipoQrWaitry('mercadopago'));
        $this->assertFalse(WaitryMedioPagoCuentacajaSupport::esTipoQrWaitry('cash'));
    }

    public function test_etiqueta_tipo_qr_para_credit_card(): void
    {
        $this->assertSame('QR (Totalcoin / tótem)', WaitryMedioPagoCuentacajaSupport::etiquetaTipo('credit_card'));
    }
}

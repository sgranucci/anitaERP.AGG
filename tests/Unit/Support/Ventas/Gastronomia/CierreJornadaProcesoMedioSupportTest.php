<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\CierreJornadaProcesoMedioSupport;
use Tests\TestCase;

final class CierreJornadaProcesoMedioSupportTest extends TestCase
{
    public function test_credit_card_posnet_clasifica_como_mp(): void
    {
        $this->assertSame(
            CierreJornadaProcesoMedioSupport::CLAVE_MP,
            CierreJornadaProcesoMedioSupport::claveDesdeWaitryTipo('credit_card'),
        );
        $this->assertFalse(CierreJornadaProcesoMedioSupport::esWaitryQr('credit_card'));
    }

    public function test_credit_card_mpqr_clasifica_como_qr(): void
    {
        $this->assertSame(
            CierreJornadaProcesoMedioSupport::CLAVE_QR,
            CierreJornadaProcesoMedioSupport::claveDesdeWaitryTipo('credit_card', 'KIOSK MPQR'),
        );
        $this->assertTrue(CierreJornadaProcesoMedioSupport::esWaitryQr('credit_card', 'KIOSK MPQR'));
    }

    public function test_mercadopago_y_posnet_son_redistribuibles_sin_facturar(): void
    {
        $this->assertTrue(CierreJornadaProcesoMedioSupport::esWaitryMp('mercadopago'));
        $this->assertTrue(CierreJornadaProcesoMedioSupport::esWaitryMp('credit_card'));
        $this->assertTrue(CierreJornadaProcesoMedioSupport::esWaitrySinFacturarRedistribuible('mercadopago'));
        $this->assertTrue(CierreJornadaProcesoMedioSupport::esWaitrySinFacturarRedistribuible('credit_card'));
        $this->assertFalse(CierreJornadaProcesoMedioSupport::esWaitrySinFacturarRedistribuible('cash'));
    }
}

<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\CierreJornadaProcesoMedioSupport;
use Tests\TestCase;

final class CierreJornadaProcesoMedioSupportTest extends TestCase
{
    public function test_credit_card_waitry_clasifica_como_qr(): void
    {
        $this->assertSame(
            CierreJornadaProcesoMedioSupport::CLAVE_QR,
            CierreJornadaProcesoMedioSupport::claveDesdeWaitryTipo('credit_card'),
        );
        $this->assertTrue(CierreJornadaProcesoMedioSupport::esWaitryQr('credit_card'));
    }
}

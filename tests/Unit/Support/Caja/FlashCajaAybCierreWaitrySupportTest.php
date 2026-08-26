<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\Flash\FlashCajaAybCierreWaitrySupport;
use Tests\TestCase;

class FlashCajaAybCierreWaitrySupportTest extends TestCase
{
    public function test_sin_caea_es_faltante(): void
    {
        $this->assertSame(
            FlashCajaAybCierreWaitrySupport::NIVEL_FALTANTE,
            FlashCajaAybCierreWaitrySupport::clasificar(false, 1000.0, 1000.0)
        );
    }

    public function test_caea_incluido_cuando_ayb_alcanza_erp(): void
    {
        $this->assertSame(
            FlashCajaAybCierreWaitrySupport::NIVEL_INCLUIDO,
            FlashCajaAybCierreWaitrySupport::clasificar(true, 1809200.97, 1809200.97)
        );
    }

    public function test_caea_corto_cuando_ayb_no_llega_al_erp(): void
    {
        $this->assertSame(
            FlashCajaAybCierreWaitrySupport::NIVEL_CORTO,
            FlashCajaAybCierreWaitrySupport::clasificar(true, 1795900.97, 1809200.97)
        );
    }

    public function test_mensaje_faltante_advierte_no_grabar_corto(): void
    {
        $msg = FlashCajaAybCierreWaitrySupport::mensaje(
            FlashCajaAybCierreWaitrySupport::NIVEL_FALTANTE,
            0,
            0
        );

        $this->assertStringContainsString('Todavía no está el CAEA', $msg);
        $this->assertStringContainsString('puede quedar corto', $msg);
    }

    public function test_armar_aviso_corto_incluye_monto(): void
    {
        $aviso = FlashCajaAybCierreWaitrySupport::armarAviso(
            ['tiene' => true, 'monto' => 13300.0, 'cantidad' => 1],
            1795900.97,
            1809200.97,
        );

        $this->assertSame(FlashCajaAybCierreWaitrySupport::NIVEL_CORTO, $aviso['nivel']);
        $this->assertStringContainsString('13.300,00', $aviso['mensaje']);
        $this->assertSame(13300.0, $aviso['monto_caea']);
    }
}

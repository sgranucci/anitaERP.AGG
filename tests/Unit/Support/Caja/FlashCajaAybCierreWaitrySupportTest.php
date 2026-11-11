<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\Flash\FlashCajaAybCierreWaitrySupport;
use Tests\TestCase;

class FlashCajaAybCierreWaitrySupportTest extends TestCase
{
    public function test_sin_proceso_es_faltante(): void
    {
        $this->assertSame(
            FlashCajaAybCierreWaitrySupport::NIVEL_FALTANTE,
            FlashCajaAybCierreWaitrySupport::clasificar(false, false, 1000.0, 1000.0)
        );
    }

    public function test_proceso_sin_caea_es_incluido(): void
    {
        $this->assertSame(
            FlashCajaAybCierreWaitrySupport::NIVEL_INCLUIDO,
            FlashCajaAybCierreWaitrySupport::clasificar(true, false, 1410401.18, 1410401.18)
        );
    }

    public function test_proceso_con_caea_incluido_cuando_ayb_alcanza_erp(): void
    {
        $this->assertSame(
            FlashCajaAybCierreWaitrySupport::NIVEL_INCLUIDO,
            FlashCajaAybCierreWaitrySupport::clasificar(true, true, 1809200.97, 1809200.97)
        );
    }

    public function test_proceso_con_caea_corto_cuando_ayb_no_llega_al_erp(): void
    {
        $this->assertSame(
            FlashCajaAybCierreWaitrySupport::NIVEL_CORTO,
            FlashCajaAybCierreWaitrySupport::clasificar(true, true, 1795900.97, 1809200.97)
        );
    }

    public function test_mensaje_faltante_advierte_proceso_pendiente(): void
    {
        $msg = FlashCajaAybCierreWaitrySupport::mensaje(
            FlashCajaAybCierreWaitrySupport::NIVEL_FALTANTE,
            0,
            0
        );

        $this->assertStringContainsString('Todavía no terminó el cierre Waitry', $msg);
        $this->assertStringContainsString('puede quedar corto', $msg);
    }

    public function test_mensaje_incluido_sin_caea_no_menciona_factura(): void
    {
        $msg = FlashCajaAybCierreWaitrySupport::mensaje(
            FlashCajaAybCierreWaitrySupport::NIVEL_INCLUIDO,
            0,
            0,
            false,
        );

        $this->assertSame('El cierre Waitry de esta jornada ya terminó.', $msg);
    }

    public function test_armar_aviso_corto_incluye_monto(): void
    {
        $aviso = FlashCajaAybCierreWaitrySupport::armarAviso(
            true,
            ['tiene' => true, 'monto' => 13300.0, 'cantidad' => 1],
            1795900.97,
            1809200.97,
        );

        $this->assertSame(FlashCajaAybCierreWaitrySupport::NIVEL_CORTO, $aviso['nivel']);
        $this->assertStringContainsString('13.300,00', $aviso['mensaje']);
        $this->assertSame(13300.0, $aviso['monto_caea']);
        $this->assertTrue($aviso['proceso_completado']);
    }

    public function test_armar_aviso_proceso_completo_sin_caea_es_incluido(): void
    {
        $aviso = FlashCajaAybCierreWaitrySupport::armarAviso(
            true,
            ['tiene' => false, 'monto' => 0.0, 'cantidad' => 0],
            1410401.18,
            1410401.18,
        );

        $this->assertSame(FlashCajaAybCierreWaitrySupport::NIVEL_INCLUIDO, $aviso['nivel']);
        $this->assertFalse($aviso['tiene_caea']);
        $this->assertTrue($aviso['proceso_completado']);
        $this->assertSame('El cierre Waitry de esta jornada ya terminó.', $aviso['mensaje']);
    }
}

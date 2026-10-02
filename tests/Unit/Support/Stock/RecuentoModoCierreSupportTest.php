<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\RecuentoModoCierreSupport;
use Carbon\Carbon;
use Tests\TestCase;

class RecuentoModoCierreSupportTest extends TestCase
{
    public function test_resolver_modo_invalido_usa_saldo_actual(): void
    {
        $this->assertSame(
            RecuentoModoCierreSupport::MODO_SALDO_ACTUAL,
            RecuentoModoCierreSupport::resolverModo('otro')
        );
    }

    public function test_etiquetas_modo(): void
    {
        $this->assertSame(
            'A fecha del recuento',
            RecuentoModoCierreSupport::etiqueta(RecuentoModoCierreSupport::MODO_FECHA_RECUENTO)
        );
        $this->assertSame(
            'Al saldo actual',
            RecuentoModoCierreSupport::etiqueta(RecuentoModoCierreSupport::MODO_SALDO_ACTUAL)
        );
    }

    public function test_rechaza_fecha_con_anio_de_dos_digitos(): void
    {
        Carbon::setTestNow('2026-10-02');

        $mensaje = RecuentoModoCierreSupport::mensajeFechaNoGrabable('0026-10-01');

        $this->assertNotNull($mensaje);
        $this->assertStringContainsString('01/10/0026', $mensaje);
        $this->assertNull(RecuentoModoCierreSupport::mensajeFechaNoGrabable('2026-10-01'));

        Carbon::setTestNow();
    }
}

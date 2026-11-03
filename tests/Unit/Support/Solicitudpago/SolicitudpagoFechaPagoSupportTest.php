<?php

namespace Tests\Unit\Support\Solicitudpago;

use App\Support\Solicitudpago\SolicitudpagoFechaPagoSupport;
use PHPUnit\Framework\TestCase;

class SolicitudpagoFechaPagoSupportTest extends TestCase
{
    public function test_fecha_vacia_no_entra_como_fecha_de_pago(): void
    {
        $this->assertNull(SolicitudpagoFechaPagoSupport::normalizarFecha(null));
        $this->assertNull(SolicitudpagoFechaPagoSupport::normalizarFecha(''));
        $this->assertNull(SolicitudpagoFechaPagoSupport::normalizarFecha('   '));
    }

    public function test_acepta_una_fecha_calendario(): void
    {
        $this->assertSame('2026-11-03', SolicitudpagoFechaPagoSupport::normalizarFecha('2026-11-03'));
        $this->assertSame('2026-11-03', SolicitudpagoFechaPagoSupport::normalizarFecha('2026-11-03T10:00'));
    }

    public function test_rechaza_una_fecha_inexistente(): void
    {
        $this->assertNull(SolicitudpagoFechaPagoSupport::normalizarFecha('2026-02-31'));
        $this->assertNull(SolicitudpagoFechaPagoSupport::normalizarFecha('03/11/2026'));
    }
};

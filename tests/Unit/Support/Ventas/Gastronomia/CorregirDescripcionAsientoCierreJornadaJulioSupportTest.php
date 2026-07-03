<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\CierreJornadaProcesoAsientosGrabacionSupport;
use App\Support\Ventas\Gastronomia\CorregirDescripcionAsientoCierreJornadaJulioSupport;
use PHPUnit\Framework\TestCase;

final class CorregirDescripcionAsientoCierreJornadaJulioSupportTest extends TestCase
{
    public function test_requiere_actualizacion_si_leyenda_legacy(): void
    {
        $support = new CorregirDescripcionAsientoCierreJornadaJulioSupport;

        $this->assertTrue($support->requiereActualizacionCabecera(
            'Cierre Waitry jornada 2026-07-01 — 1 — Waitry sin facturar (QR / Mercado Pago tras redistribución)',
        ));
    }

    public function test_no_requiere_actualizacion_si_ya_es_venta_gastronomia(): void
    {
        $support = new CorregirDescripcionAsientoCierreJornadaJulioSupport;

        $this->assertFalse($support->requiereActualizacionCabecera(
            CierreJornadaProcesoAsientosGrabacionSupport::DESCRIPCION_ASIENTO,
        ));
        $this->assertFalse($support->requiereActualizacionLinea(
            CierreJornadaProcesoAsientosGrabacionSupport::DESCRIPCION_ASIENTO,
        ));
    }

    public function test_sanitiza_desc_mov_anita(): void
    {
        $support = new CorregirDescripcionAsientoCierreJornadaJulioSupport;

        $this->assertSame(
            'Venta gastronomia',
            $support->sanitizarDescMovAnita(CierreJornadaProcesoAsientosGrabacionSupport::DESCRIPCION_ASIENTO),
        );
    }
}

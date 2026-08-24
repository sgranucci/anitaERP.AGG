<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorIibbPadronFechaSupport;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorIibbPadronFechaSupportTest extends TestCase
{
    public function test_omite_si_la_factura_es_anterior_al_padron(): void
    {
        $this->assertTrue(
            ComprobanteProveedorIibbPadronFechaSupport::omitirPorFacturaAnterior('2026-05-04', '2026-06-01')
        );
    }

    public function test_no_omite_si_la_factura_cae_en_el_padron(): void
    {
        $this->assertFalse(
            ComprobanteProveedorIibbPadronFechaSupport::omitirPorFacturaAnterior('2026-06-15', '2026-06-01')
        );
    }

    public function test_no_omite_sin_padron_descargado(): void
    {
        $this->assertFalse(
            ComprobanteProveedorIibbPadronFechaSupport::omitirPorFacturaAnterior('2026-05-04', null)
        );
    }
}

<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\PrecargaComprobanteEstados;
use Tests\TestCase;

class PrecargaComprobanteEstadosTest extends TestCase
{
    public function test_incluye_cargada_anita(): void
    {
        $this->assertContains(PrecargaComprobanteEstados::CARGADA_ANITA, PrecargaComprobanteEstados::todos());
        $this->assertSame('Ya cargadas en Anita', PrecargaComprobanteEstados::etiqueta(PrecargaComprobanteEstados::CARGADA_ANITA));
        $this->assertSame('Ya cargada en Anita', PrecargaComprobanteEstados::etiquetaRegistro(PrecargaComprobanteEstados::CARGADA_ANITA));
    }

    public function test_pendiente_puede_generar_y_marcar(): void
    {
        $this->assertTrue(PrecargaComprobanteEstados::puedeGenerarComprobante(PrecargaComprobanteEstados::PENDIENTE));
        $this->assertTrue(PrecargaComprobanteEstados::puedeMarcarCargadaAnita(PrecargaComprobanteEstados::PENDIENTE));
        $this->assertFalse(PrecargaComprobanteEstados::puedeGenerarComprobante(PrecargaComprobanteEstados::CARGADA_ANITA));
        $this->assertFalse(PrecargaComprobanteEstados::puedeMarcarCargadaAnita(PrecargaComprobanteEstados::GENERADA));
        $this->assertFalse(PrecargaComprobanteEstados::puedeMarcarCargadaAnita(PrecargaComprobanteEstados::CARGADA_ANITA));
    }
}

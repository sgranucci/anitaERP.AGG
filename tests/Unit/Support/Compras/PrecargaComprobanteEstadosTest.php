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
        $this->assertFalse(PrecargaComprobanteEstados::puedeGenerarComprobante(PrecargaComprobanteEstados::PENDIENTE_ENTREGA));
        $this->assertFalse(PrecargaComprobanteEstados::puedeMarcarCargadaAnita(PrecargaComprobanteEstados::PENDIENTE_ENTREGA));
    }

    public function test_cargada_anita_no_pendiente_carga_cxp(): void
    {
        $this->assertTrue(PrecargaComprobanteEstados::esCargadaAnita(PrecargaComprobanteEstados::CARGADA_ANITA));
        $this->assertTrue(PrecargaComprobanteEstados::esCargadaAnita('cargada_anita'));
        $this->assertFalse(PrecargaComprobanteEstados::esCargadaAnita(PrecargaComprobanteEstados::PENDIENTE));

        $this->assertTrue(PrecargaComprobanteEstados::pendienteCargaEnCxp(PrecargaComprobanteEstados::PENDIENTE));
        $this->assertTrue(PrecargaComprobanteEstados::pendienteCargaEnCxp(null));
        $this->assertTrue(PrecargaComprobanteEstados::pendienteCargaEnCxp(PrecargaComprobanteEstados::GENERADA));
        $this->assertFalse(PrecargaComprobanteEstados::pendienteCargaEnCxp(PrecargaComprobanteEstados::CARGADA_ANITA));
        $this->assertFalse(PrecargaComprobanteEstados::pendienteCargaEnCxp('ANULADA'));
        $this->assertFalse(PrecargaComprobanteEstados::pendienteCargaEnCxp(PrecargaComprobanteEstados::PENDIENTE_ENTREGA));
    }

    public function test_pendiente_entrega(): void
    {
        $this->assertTrue(PrecargaComprobanteEstados::esPendienteEntrega(PrecargaComprobanteEstados::PENDIENTE_ENTREGA));
        $this->assertTrue(PrecargaComprobanteEstados::esPendienteEntrega('pendiente_entrega'));
        $this->assertFalse(PrecargaComprobanteEstados::esPendienteEntrega(PrecargaComprobanteEstados::PENDIENTE));

        $this->assertTrue(PrecargaComprobanteEstados::puedeMarcarPendienteEntrega(PrecargaComprobanteEstados::PENDIENTE));
        $this->assertTrue(PrecargaComprobanteEstados::puedeMarcarPendienteEntrega(null));
        $this->assertTrue(PrecargaComprobanteEstados::puedeMarcarPendienteEntrega(PrecargaComprobanteEstados::GENERADA));
        $this->assertFalse(PrecargaComprobanteEstados::puedeMarcarPendienteEntrega(PrecargaComprobanteEstados::CARGADA_ANITA));
        $this->assertFalse(PrecargaComprobanteEstados::puedeMarcarPendienteEntrega(PrecargaComprobanteEstados::ANULADA));

        $this->assertSame('Pendiente de entrega', PrecargaComprobanteEstados::etiqueta(PrecargaComprobanteEstados::PENDIENTE_ENTREGA));
    }
}

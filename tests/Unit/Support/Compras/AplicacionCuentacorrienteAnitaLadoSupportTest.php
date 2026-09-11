<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\AnitaSync\AplicacionCuentacorriente\AplicacionCuentacorrienteAnitaLadoSupport;
use PHPUnit\Framework\TestCase;

class AplicacionCuentacorrienteAnitaLadoSupportTest extends TestCase
{
    public function test_comprobante_original_es_documento_anita(): void
    {
        $this->assertTrue(
            AplicacionCuentacorrienteAnitaLadoSupport::propiaEsDocumentoDelPago(24115, 0, 24115)
        );
    }

    public function test_fila_debe_de_la_op_no_es_el_documento_anita(): void
    {
        $this->assertFalse(
            AplicacionCuentacorrienteAnitaLadoSupport::propiaEsDocumentoDelPago(24115, 24115, 0)
        );
    }

    public function test_opa_original_no_es_fila_de_esta_op(): void
    {
        $this->assertTrue(
            AplicacionCuentacorrienteAnitaLadoSupport::propiaEsDocumentoDelPago(24115, 88, 24115)
        );
    }
}

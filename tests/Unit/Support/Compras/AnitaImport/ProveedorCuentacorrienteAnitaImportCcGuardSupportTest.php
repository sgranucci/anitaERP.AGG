<?php

namespace Tests\Unit\Support\Compras\AnitaImport;

use App\Support\Compras\AnitaImport\ProveedorCuentacorrienteAnitaImportCcGuardSupport;
use PHPUnit\Framework\TestCase;
use stdClass;

class ProveedorCuentacorrienteAnitaImportCcGuardSupportTest extends TestCase
{
    public function test_deuda_documento_sin_pagoproveedor(): void
    {
        $cc = new stdClass;
        $cc->pagoproveedor_id = null;
        $this->assertTrue(ProveedorCuentacorrienteAnitaImportCcGuardSupport::esDeudaDocumento($cc));

        $cc->pagoproveedor_id = 0;
        $this->assertTrue(ProveedorCuentacorrienteAnitaImportCcGuardSupport::esDeudaDocumento($cc));
    }

    public function test_fila_de_pago_no_es_deuda_documento(): void
    {
        $cc = new stdClass;
        $cc->pagoproveedor_id = 24124;
        $this->assertFalse(ProveedorCuentacorrienteAnitaImportCcGuardSupport::esDeudaDocumento($cc));
        $this->assertFalse(ProveedorCuentacorrienteAnitaImportCcGuardSupport::esDeudaDocumento(null));
    }

    public function test_puede_alinear_solo_deuda_sin_app_operativa(): void
    {
        $deuda = new stdClass;
        $deuda->pagoproveedor_id = null;
        $this->assertTrue(
            ProveedorCuentacorrienteAnitaImportCcGuardSupport::puedeAlinearSaldo($deuda, false)
        );
        $this->assertFalse(
            ProveedorCuentacorrienteAnitaImportCcGuardSupport::puedeAlinearSaldo($deuda, true)
        );

        $pago = new stdClass;
        $pago->pagoproveedor_id = 24124;
        $this->assertFalse(
            ProveedorCuentacorrienteAnitaImportCcGuardSupport::puedeAlinearSaldo($pago, false)
        );
    }

    public function test_sql_and_solo_deuda_documento(): void
    {
        $this->assertSame(
            ' AND cc.pagoproveedor_id IS NULL',
            ProveedorCuentacorrienteAnitaImportCcGuardSupport::sqlAndSoloDeudaDocumento()
        );
        $this->assertSame(
            ' AND c.pagoproveedor_id IS NULL',
            ProveedorCuentacorrienteAnitaImportCcGuardSupport::sqlAndSoloDeudaDocumento('c')
        );
    }
}

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

    public function test_no_reaplica_si_el_saldo_ya_es_el_pendiente_de_anita(): void
    {
        $cupo = ProveedorCuentacorrienteAnitaImportCcGuardSupport::cupoAplicacionSobreSaldo(400.0, 400.0);

        $this->assertFalse($cupo['aplicar']);
        $this->assertSame(0.0, $cupo['a_cubrir']);
        $this->assertFalse($cupo['reabrir']);
    }

    public function test_aplica_solo_el_hueco_entre_el_erp_y_anita(): void
    {
        $cupo = ProveedorCuentacorrienteAnitaImportCcGuardSupport::cupoAplicacionSobreSaldo(1000.0, 400.0);

        $this->assertTrue($cupo['aplicar']);
        $this->assertSame(600.0, $cupo['a_cubrir']);
        $this->assertFalse($cupo['reabrir']);
    }

    public function test_no_reabre_si_el_erp_esta_mas_cerrado_que_anita(): void
    {
        $cupo = ProveedorCuentacorrienteAnitaImportCcGuardSupport::cupoAplicacionSobreSaldo(200.0, 400.0);

        $this->assertFalse($cupo['aplicar']);
        $this->assertTrue($cupo['reabrir']);
    }

    public function test_pagado_mayor_que_la_cuota_no_la_da_por_cobrada(): void
    {
        $this->assertSame(
            0.0,
            ProveedorCuentacorrienteAnitaImportCcGuardSupport::pagadoAplicableACuota(36432581.21, 364325812.10)
        );
        $this->assertSame(
            36432581.21,
            ProveedorCuentacorrienteAnitaImportCcGuardSupport::pagadoAplicableACuota(36432581.21, 36432581.21)
        );
        $this->assertSame(
            0.0,
            ProveedorCuentacorrienteAnitaImportCcGuardSupport::pagadoAplicableACuota(36432581.21, 0)
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

<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\OrdencompraEnvioCuentasAPagarGateSupport;
use PHPUnit\Framework\TestCase;

class OrdencompraEnvioCuentasAPagarFacturasErpTest extends TestCase
{
    public function test_bloquea_cuando_todas_las_facturas_estan_en_el_erp(): void
    {
        $this->assertTrue(OrdencompraEnvioCuentasAPagarGateSupport::debeBloquearEnvioPorFacturasYaEnErp(
            false,
            false,
            true,
            false,
        ));
    }

    public function test_permite_si_queda_alguna_factura_por_cargar(): void
    {
        $this->assertFalse(OrdencompraEnvioCuentasAPagarGateSupport::debeBloquearEnvioPorFacturasYaEnErp(
            true,
            false,
            true,
            false,
        ));
    }

    public function test_permite_si_hay_factura_retenida_por_entrega(): void
    {
        $this->assertFalse(OrdencompraEnvioCuentasAPagarGateSupport::debeBloquearEnvioPorFacturasYaEnErp(
            false,
            true,
            true,
            false,
        ));
    }

    public function test_permite_si_no_hay_comprobante_en_el_erp(): void
    {
        $this->assertFalse(OrdencompraEnvioCuentasAPagarGateSupport::debeBloquearEnvioPorFacturasYaEnErp(
            false,
            false,
            false,
            false,
        ));
    }

    public function test_permite_si_queda_una_precarga_solo_en_anita(): void
    {
        $this->assertFalse(OrdencompraEnvioCuentasAPagarGateSupport::debeBloquearEnvioPorFacturasYaEnErp(
            false,
            false,
            true,
            true,
        ));
    }
}

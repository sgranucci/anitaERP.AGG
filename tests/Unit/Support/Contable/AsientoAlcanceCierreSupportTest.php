<?php

namespace Tests\Unit\Support\Contable;

use App\Support\Contable\AsientoAlcanceCierreSupport;
use App\Support\Contable\PeriodoContableCierreSupport;
use Tests\TestCase;

class AsientoAlcanceCierreSupportTest extends TestCase
{
    public function test_modificar_asiento_de_compras_depende_del_cierre_contable(): void
    {
        $data = [
            'comprobante_proveedor_id' => 15,
            'alcance_cierre_contable' => PeriodoContableCierreSupport::ALCANCE_CUENTAS_PAGAR,
        ];

        $this->assertSame(
            PeriodoContableCierreSupport::ALCANCE_CONTABLE,
            AsientoAlcanceCierreSupport::alcanceParaValidar($data, true)
        );
    }

    public function test_modificar_asiento_de_venta_o_stock_depende_del_cierre_contable(): void
    {
        $this->assertSame(
            PeriodoContableCierreSupport::ALCANCE_CONTABLE,
            AsientoAlcanceCierreSupport::alcanceParaValidar(['venta_id' => 9], true)
        );
        $this->assertSame(
            PeriodoContableCierreSupport::ALCANCE_CONTABLE,
            AsientoAlcanceCierreSupport::alcanceParaValidar(['cobranza_id' => 4], true)
        );
        $this->assertSame(
            PeriodoContableCierreSupport::ALCANCE_CONTABLE,
            AsientoAlcanceCierreSupport::alcanceParaValidar(['recepcionproveedor_id' => 7], true)
        );
    }

    public function test_alta_de_factura_de_proveedor_sigue_el_cierre_de_compras(): void
    {
        $data = [
            'comprobante_proveedor_id' => 15,
            'alcance_cierre_contable' => PeriodoContableCierreSupport::ALCANCE_CUENTAS_PAGAR,
        ];

        $this->assertSame(
            PeriodoContableCierreSupport::ALCANCE_CUENTAS_PAGAR,
            AsientoAlcanceCierreSupport::alcanceParaValidar($data, false)
        );
        $this->assertTrue(AsientoAlcanceCierreSupport::tieneAlcanceExplicito($data));
    }

    public function test_alta_sin_alcance_explicito_infiere_el_origen(): void
    {
        $this->assertSame(
            PeriodoContableCierreSupport::ALCANCE_FACTURACION,
            AsientoAlcanceCierreSupport::alcanceParaValidar(['venta_id' => 3], false)
        );
        $this->assertFalse(AsientoAlcanceCierreSupport::tieneAlcanceExplicito(['venta_id' => 3]));
    }
}

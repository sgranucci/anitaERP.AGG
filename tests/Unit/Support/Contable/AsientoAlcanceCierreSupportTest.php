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

    public function test_alta_manual_con_factura_de_proveedor_sigue_el_cierre_contable(): void
    {
        $data = [
            'comprobante_proveedor_id' => 15,
            'ordencompra_id' => 8,
            'alcance_cierre_contable' => PeriodoContableCierreSupport::ALCANCE_CONTABLE,
        ];

        $this->assertSame(
            PeriodoContableCierreSupport::ALCANCE_CONTABLE,
            AsientoAlcanceCierreSupport::alcanceParaValidar($data, false)
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

    public function test_alta_con_cualquier_referencia_sigue_el_cierre_contable(): void
    {
        $referencias = [
            ['venta_id' => 3],
            ['cobranza_id' => 4],
            ['caja_movimiento_id' => 5],
            ['movimientostock_id' => 6],
            ['recepcionproveedor_id' => 7],
            ['comprobante_proveedor_id' => 15, 'ordencompra_id' => 8],
            ['liquidacion_sueldos_id' => 2],
            [],
        ];

        foreach ($referencias as $data) {
            $this->assertSame(
                PeriodoContableCierreSupport::ALCANCE_CONTABLE,
                AsientoAlcanceCierreSupport::alcanceParaValidar($data, false)
            );
            $this->assertFalse(AsientoAlcanceCierreSupport::tieneAlcanceExplicito($data));
        }
    }
}

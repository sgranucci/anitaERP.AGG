<?php

namespace Tests\Unit\Support\Contable\MayorPlanoCuenta;

use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaVentasFiltroSupport;
use PHPUnit\Framework\TestCase;

class MayorPlanoCuentaVentasFiltroSupportTest extends TestCase
{
    public function test_sistema_v_es_venta(): void
    {
        $this->assertTrue(MayorPlanoCuentaVentasFiltroSupport::esMovimientoVentas([
            'sistema' => 'V',
        ]));
    }

    public function test_tipo_asiento_vta_es_venta(): void
    {
        $this->assertTrue(MayorPlanoCuentaVentasFiltroSupport::esMovimientoVentas([
            'sistema' => 'C',
            'tipo_asiento' => 'VTA',
        ]));
    }

    public function test_venta_id_en_fk_es_venta(): void
    {
        $this->assertTrue(MayorPlanoCuentaVentasFiltroSupport::esMovimientoVentas([
            'sistema' => '',
            'erp_asiento_fks' => ['venta_id' => 88],
        ]));
    }

    public function test_compras_no_es_venta(): void
    {
        $this->assertFalse(MayorPlanoCuentaVentasFiltroSupport::esMovimientoVentas([
            'sistema' => 'C',
            'tipo_asiento' => '',
            'erp_asiento_fks' => ['comprobante_proveedor_id' => 12],
        ]));
    }

    public function test_condicion_sql_sistema_valida_columna(): void
    {
        $this->assertSame(" AND subd_sistema='V'", MayorPlanoCuentaVentasFiltroSupport::condicionSqlSistema('subd_sistema'));
        $this->assertSame('', MayorPlanoCuentaVentasFiltroSupport::condicionSqlSistema('subd_sistema;drop'));
    }
}

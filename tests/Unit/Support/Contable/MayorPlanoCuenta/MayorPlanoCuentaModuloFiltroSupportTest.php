<?php

namespace Tests\Unit\Support\Contable\MayorPlanoCuenta;

use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaModuloFiltroSupport;
use PHPUnit\Framework\TestCase;

class MayorPlanoCuentaModuloFiltroSupportTest extends TestCase
{
    public function test_sistema_c_es_compras(): void
    {
        $mov = ['sistema' => 'C', 'tipo_comp' => 'FAC'];

        $this->assertTrue(MayorPlanoCuentaModuloFiltroSupport::esMovimiento($mov, 'compras'));
        $this->assertFalse(MayorPlanoCuentaModuloFiltroSupport::esMovimiento($mov, 'caja'));
        $this->assertFalse(MayorPlanoCuentaModuloFiltroSupport::esMovimiento($mov, 'ventas'));
    }

    public function test_orden_de_pago_es_cuentas_a_pagar_y_no_caja(): void
    {
        $mov = ['sistema' => 'T', 'tipo_comp' => 'OPP', 'tipo_asiento' => 'TES'];

        $this->assertTrue(MayorPlanoCuentaModuloFiltroSupport::esMovimiento($mov, 'cuentas_pagar'));
        $this->assertFalse(MayorPlanoCuentaModuloFiltroSupport::esMovimiento($mov, 'caja'));
        $this->assertFalse(MayorPlanoCuentaModuloFiltroSupport::esMovimiento($mov, 'compras'));
    }

    public function test_tesoreria_fac_es_caja(): void
    {
        $mov = ['sistema' => 'T', 'tipo_comp' => 'FAC', 'tipo_asiento' => 'TES'];

        $this->assertTrue(MayorPlanoCuentaModuloFiltroSupport::esMovimiento($mov, 'caja'));
        $this->assertFalse(MayorPlanoCuentaModuloFiltroSupport::esMovimiento($mov, 'cuentas_pagar'));
    }

    public function test_movimiento_de_caja_nativo(): void
    {
        $mov = [
            'sistema' => '',
            'tipo_asiento' => 'TES',
            'erp_asiento_fks' => ['caja_movimiento_id' => 15],
        ];

        $this->assertTrue(MayorPlanoCuentaModuloFiltroSupport::esMovimiento($mov, 'caja'));
    }

    public function test_sql_anita_por_modulo(): void
    {
        $this->assertSame(
            " AND subd_sistema='V'",
            MayorPlanoCuentaModuloFiltroSupport::condicionSqlAnita('subd_sistema', 'subd_tipo', 'ventas'),
        );
        $this->assertSame(
            " AND subd_sistema='C'",
            MayorPlanoCuentaModuloFiltroSupport::condicionSqlAnita('subd_sistema', 'subd_tipo', 'compras'),
        );
        $this->assertSame(
            " AND ctav_sistema='T' AND TRIM(ctav_tipo) IN ('OPP','OPA','APA')",
            MayorPlanoCuentaModuloFiltroSupport::condicionSqlAnita('ctav_sistema', 'ctav_tipo', 'cuentas_pagar'),
        );
        $this->assertStringContainsString(
            "subd_sistema='T'",
            MayorPlanoCuentaModuloFiltroSupport::condicionSqlAnita('subd_sistema', 'subd_tipo', 'caja'),
        );
        $this->assertSame('', MayorPlanoCuentaModuloFiltroSupport::condicionSqlAnita('subd_sistema;drop', 'subd_tipo', 'compras'));
    }

    public function test_modulo_invalido_queda_en_todos(): void
    {
        $this->assertSame('', MayorPlanoCuentaModuloFiltroSupport::normalizar('stock'));
        $this->assertTrue(MayorPlanoCuentaModuloFiltroSupport::esMovimiento(['sistema' => 'B'], 'no-existe'));
    }
}

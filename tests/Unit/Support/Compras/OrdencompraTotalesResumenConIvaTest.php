<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\OrdencompraTotalesResumen;
use PHPUnit\Framework\TestCase;

class OrdencompraTotalesResumenConIvaTest extends TestCase
{
    public function test_coniva_n_desactiva_iva(): void
    {
        $this->assertFalse(OrdencompraTotalesResumen::flConIvaDesdeConiva('N'));
        $this->assertFalse(OrdencompraTotalesResumen::flConIvaDesdeConiva('n'));
        $this->assertFalse(OrdencompraTotalesResumen::flConIvaDesdeConiva(' N '));
    }

    public function test_coniva_s_u_otro_activa_iva(): void
    {
        $this->assertTrue(OrdencompraTotalesResumen::flConIvaDesdeConiva('S'));
        $this->assertTrue(OrdencompraTotalesResumen::flConIvaDesdeConiva(''));
        $this->assertTrue(OrdencompraTotalesResumen::flConIvaDesdeConiva(null));
    }

    public function test_proveedor_id_invalido_conserva_iva(): void
    {
        $this->assertTrue(OrdencompraTotalesResumen::flConIvaDesdeProveedorId(null));
        $this->assertTrue(OrdencompraTotalesResumen::flConIvaDesdeProveedorId(0));
        $this->assertTrue(OrdencompraTotalesResumen::flConIvaDesdeProveedorId(-1));
    }
}

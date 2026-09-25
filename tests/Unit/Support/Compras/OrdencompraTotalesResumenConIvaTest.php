<?php

namespace Tests\Unit\Support\Compras;

use App\Models\Configuracion\Condicioniva;
use App\Support\Compras\OrdencompraTotalesResumen;
use Tests\TestCase;

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

    public function test_condicioniva_null_conserva_iva(): void
    {
        $this->assertTrue(OrdencompraTotalesResumen::flConIvaDesdeCondicioniva(null));
    }

    public function test_monotributo_a_clientes_coniva_s_sin_iva_en_compras(): void
    {
        $iva = new Condicioniva;
        $iva->id = 4;
        $iva->nombre = 'Monotributo A Clientes';
        $iva->coniva = 'S';

        $this->assertFalse(OrdencompraTotalesResumen::flConIvaDesdeCondicioniva($iva));
    }

    public function test_monotributo_c_proveedores_coniva_n_sin_iva(): void
    {
        $iva = new Condicioniva;
        $iva->id = 8;
        $iva->nombre = 'Monotributo C Proveedores';
        $iva->coniva = 'N';

        $this->assertFalse(OrdencompraTotalesResumen::flConIvaDesdeCondicioniva($iva));
    }

    public function test_responsable_inscripto_coniva_s_conserva_iva(): void
    {
        $iva = new Condicioniva;
        $iva->id = 1;
        $iva->nombre = 'Responsable Inscripto';
        $iva->coniva = 'S';

        $this->assertTrue(OrdencompraTotalesResumen::flConIvaDesdeCondicioniva($iva));
    }

    public function test_exento_coniva_n_sin_iva(): void
    {
        $iva = new Condicioniva;
        $iva->id = 5;
        $iva->nombre = 'Exento';
        $iva->coniva = 'N';

        $this->assertFalse(OrdencompraTotalesResumen::flConIvaDesdeCondicioniva($iva));
    }
}

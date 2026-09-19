<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\AnitaSync\Pagoproveedor\PagoproveedorAnitaNumeracionSupport;
use Tests\TestCase;

class PagoproveedorAnitaNumeracionSupportTest extends TestCase
{
    public function test_pago_c_multiempresa_opa_usa_o_empresa_no_opa(): void
    {
        config(['pagoproveedor.anita_multiempresa' => true]);

        $this->assertSame('O1', PagoproveedorAnitaNumeracionSupport::claveTCompParaTipo('OPA', 1));
        $this->assertSame('O2', PagoproveedorAnitaNumeracionSupport::claveTCompParaTipo('OPP', 2));
        $this->assertSame('O3', PagoproveedorAnitaNumeracionSupport::claveTCompParaTipo('OPA', 3));
    }

    public function test_ferli_numera_opa_con_tcomp_opa(): void
    {
        config([
            'pagoproveedor.anita_multiempresa' => false,
            'pagoproveedor.anita_tcomp_clave' => 'OPP',
            'pagoproveedor.anita_tcomp_clave_opa' => 'OPA',
        ]);

        $this->assertSame('OPA', PagoproveedorAnitaNumeracionSupport::claveTCompParaTipo('OPA', 1));
        $this->assertSame('OPP', PagoproveedorAnitaNumeracionSupport::claveTCompParaTipo('OPP', 1));
        $this->assertSame(
            'OPA',
            PagoproveedorAnitaNumeracionSupport::claveTCompParaTipo(
                PagoproveedorAnitaNumeracionSupport::normalizarTipoComprobante('OPP', true),
                1
            )
        );
    }

    public function test_adelanto_sin_aplicaciones_normaliza_a_opa(): void
    {
        $this->assertSame(
            'OPA',
            PagoproveedorAnitaNumeracionSupport::normalizarTipoComprobante('OPP', true)
        );
        $this->assertSame(
            'OPP',
            PagoproveedorAnitaNumeracionSupport::normalizarTipoComprobante('OPP', false)
        );
        $this->assertSame(
            'OPA',
            PagoproveedorAnitaNumeracionSupport::normalizarTipoComprobante('OPA', false)
        );
    }
}

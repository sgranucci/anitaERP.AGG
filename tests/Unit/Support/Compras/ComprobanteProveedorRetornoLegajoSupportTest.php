<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorRetornoLegajoSupport;
use Illuminate\Http\Request;
use Tests\TestCase;

class ComprobanteProveedorRetornoLegajoSupportTest extends TestCase
{
    public function test_origen_bandeja_o_oc(): void
    {
        $this->assertSame(
            'legajo',
            ComprobanteProveedorRetornoLegajoSupport::origenDesdeRequest(Request::create('/', 'GET', ['origen' => 'legajo']))
        );
        $this->assertSame(
            'oc',
            ComprobanteProveedorRetornoLegajoSupport::origenDesdeRequest(Request::create('/', 'GET', ['origen' => 'oc']))
        );
        $this->assertNull(
            ComprobanteProveedorRetornoLegajoSupport::origenDesdeRequest(Request::create('/', 'GET', ['origen' => 'otro']))
        );
    }

    public function test_query_params_solo_si_viene_del_legajo(): void
    {
        $this->assertSame([], ComprobanteProveedorRetornoLegajoSupport::queryParams(
            Request::create('/', 'GET')
        ));
        $this->assertSame(
            ['origen' => 'legajo', 'ordencompra_id' => 9],
            ComprobanteProveedorRetornoLegajoSupport::queryParams(
                Request::create('/', 'GET', ['origen' => 'legajo', 'ordencompra_id' => 9])
            )
        );
    }
}

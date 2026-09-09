<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorRetornoLegajoSupport;
use Illuminate\Http\Request;
use Tests\TestCase;

class ComprobanteProveedorRetornoLegajoSupportTest extends TestCase
{
    public function test_origen_bandeja_oc_o_precarga(): void
    {
        $this->assertSame(
            'legajo',
            ComprobanteProveedorRetornoLegajoSupport::origenDesdeRequest(Request::create('/', 'GET', ['origen' => 'legajo']))
        );
        $this->assertSame(
            'oc',
            ComprobanteProveedorRetornoLegajoSupport::origenDesdeRequest(Request::create('/', 'GET', ['origen' => 'oc']))
        );
        $this->assertSame(
            'precarga',
            ComprobanteProveedorRetornoLegajoSupport::origenDesdeRequest(Request::create('/', 'GET', ['origen' => 'precarga']))
        );
        $this->assertNull(
            ComprobanteProveedorRetornoLegajoSupport::origenDesdeRequest(Request::create('/', 'GET', ['origen' => 'otro']))
        );
    }

    public function test_query_params_solo_si_viene_del_legajo_o_precarga(): void
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
        $this->assertSame(
            ['origen' => 'precarga'],
            ComprobanteProveedorRetornoLegajoSupport::queryParams(
                Request::create('/', 'GET', ['origen' => 'precarga'])
            )
        );
    }

    public function test_para_vista_precarga(): void
    {
        $vista = ComprobanteProveedorRetornoLegajoSupport::paraVista(
            Request::create('/', 'GET', ['origen' => 'precarga'])
        );

        $this->assertIsArray($vista);
        $this->assertSame('precarga', $vista['origen']);
        $this->assertSame('Volver a precargas', $vista['etiqueta']);
        $this->assertSame(route('precarga_comprobante_proveedor'), $vista['url']);
    }
}

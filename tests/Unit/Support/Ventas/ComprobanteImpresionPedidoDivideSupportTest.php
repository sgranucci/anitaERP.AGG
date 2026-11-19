<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\ComprobanteImpresionFormulario;
use App\Support\Ventas\ComprobanteImpresionPedidoDivideSupport;
use Tests\TestCase;

class ComprobanteImpresionPedidoDivideSupportTest extends TestCase
{
    public function test_fuera_de_el_bierzo_siempre_incluye_pedido(): void
    {
        config()->set('app.empresa', 'AGG');

        $programa = (object) ['pedido_solo_si_cliente_divide' => true];
        $cliente = (object) ['coeficientes' => null];

        $this->assertTrue(ComprobanteImpresionPedidoDivideSupport::debeIncluirPedido($programa, $cliente));
    }

    public function test_con_tilde_y_sin_coeficiente_omite_pedido(): void
    {
        config()->set('app.empresa', 'EL BIERZO');

        $programa = (object) ['pedido_solo_si_cliente_divide' => true];
        $cliente = (object) ['coeficientes' => null];

        $this->assertFalse(ComprobanteImpresionPedidoDivideSupport::debeIncluirPedido($programa, $cliente));
    }

    public function test_con_tilde_y_coeficiente_incluye_pedido(): void
    {
        config()->set('app.empresa', 'EL BIERZO');

        $programa = (object) ['pedido_solo_si_cliente_divide' => true];
        $cliente = (object) [
            'coeficientes' => (object) ['porcentajedivision' => 50, 'tasa' => 0],
        ];

        $this->assertTrue(ComprobanteImpresionPedidoDivideSupport::debeIncluirPedido($programa, $cliente));
    }

    public function test_tilde_apagado_incluye_aunque_no_divida(): void
    {
        config()->set('app.empresa', 'EL BIERZO');

        $programa = (object) ['pedido_solo_si_cliente_divide' => false];
        $cliente = (object) ['coeficientes' => null];

        $this->assertTrue(ComprobanteImpresionPedidoDivideSupport::debeIncluirPedido($programa, $cliente));
    }

    public function test_pedido_explicito_no_se_filtra(): void
    {
        config()->set('app.empresa', 'EL BIERZO');

        $programa = (object) ['pedido_solo_si_cliente_divide' => true];
        $cliente = (object) ['coeficientes' => null];

        $this->assertTrue(ComprobanteImpresionPedidoDivideSupport::debeIncluirPedido(
            $programa,
            $cliente,
            ComprobanteImpresionFormulario::PEDIDO
        ));
    }

    public function test_filtrar_documentos_quita_solo_pedido(): void
    {
        config()->set('app.empresa', 'EL BIERZO');

        $docs = [
            ComprobanteImpresionFormulario::FACTURA => ['id' => 1],
            ComprobanteImpresionFormulario::PEDIDO => ['id' => 2],
            ComprobanteImpresionFormulario::REMITO => ['id' => 3],
        ];
        $programa = (object) ['pedido_solo_si_cliente_divide' => true];
        $cliente = (object) ['coeficientes' => null];

        $filtrados = ComprobanteImpresionPedidoDivideSupport::filtrarDocumentos($docs, $programa, $cliente);

        $this->assertArrayHasKey(ComprobanteImpresionFormulario::FACTURA, $filtrados);
        $this->assertArrayHasKey(ComprobanteImpresionFormulario::REMITO, $filtrados);
        $this->assertArrayNotHasKey(ComprobanteImpresionFormulario::PEDIDO, $filtrados);
    }
}

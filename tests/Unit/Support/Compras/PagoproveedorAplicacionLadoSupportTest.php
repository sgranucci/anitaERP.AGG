<?php

namespace Tests\Unit\Support\Compras;

use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Support\Compras\PagoproveedorAplicacionLadoSupport;
use App\Support\Compras\ProveedorCuentacorrienteGrillaSupport;
use PHPUnit\Framework\TestCase;

class PagoproveedorAplicacionLadoSupportTest extends TestCase
{
    public function test_factura_suma_y_retiene(): void
    {
        $cc = $this->cc(40301509.68, null, 100);

        $this->assertSame(1, PagoproveedorAplicacionLadoSupport::signo($cc));
        $this->assertFalse(PagoproveedorAplicacionLadoSupport::esCredito($cc));
        $this->assertFalse(PagoproveedorAplicacionLadoSupport::esOpa($cc));
        $this->assertTrue(PagoproveedorAplicacionLadoSupport::afectaRetenciones($cc));
    }

    public function test_nc_resta_y_retiene(): void
    {
        $cc = $this->cc(-79958.25, null, 200);

        $this->assertSame(-1, PagoproveedorAplicacionLadoSupport::signo($cc));
        $this->assertTrue(PagoproveedorAplicacionLadoSupport::esCredito($cc));
        $this->assertFalse(PagoproveedorAplicacionLadoSupport::esOpa($cc));
        $this->assertTrue(PagoproveedorAplicacionLadoSupport::afectaRetenciones($cc));
    }

    public function test_opa_resta_y_no_retiene(): void
    {
        $cc = $this->cc(-500000.0, 88, null);

        $this->assertSame(-1, PagoproveedorAplicacionLadoSupport::signo($cc));
        $this->assertTrue(PagoproveedorAplicacionLadoSupport::esCredito($cc));
        $this->assertTrue(PagoproveedorAplicacionLadoSupport::esOpa($cc));
        $this->assertFalse(PagoproveedorAplicacionLadoSupport::afectaRetenciones($cc));
    }

    public function test_comprobantes_a_pagar_acepta_factura_nc_y_opa(): void
    {
        $this->assertTrue(PagoproveedorAplicacionLadoSupport::esAplicableEnOrdenDePago(
            $this->cc(1000.0, null, 10)
        ));
        $this->assertTrue(PagoproveedorAplicacionLadoSupport::esAplicableEnOrdenDePago(
            $this->cc(-500.0, null, 20)
        ));
        $this->assertTrue(PagoproveedorAplicacionLadoSupport::esAplicableEnOrdenDePago(
            $this->ccConTipoPago(-800.0, 88, 'OPA')
        ));
    }

    public function test_comprobantes_a_pagar_rechaza_residual_opp_y_aop(): void
    {
        $this->assertFalse(PagoproveedorAplicacionLadoSupport::esAplicableEnOrdenDePago(
            $this->ccConTipoPago(-800.0, 88, 'OPP')
        ));
        $this->assertFalse(PagoproveedorAplicacionLadoSupport::esAplicableEnOrdenDePago(
            $this->ccConTipoPago(-800.0, 88, 'AOP')
        ));
        $this->assertFalse(PagoproveedorAplicacionLadoSupport::esAplicableEnOrdenDePago(
            $this->ccConTipoPago(-800.0, 88, '')
        ));
    }

    public function test_factura_op_abre_debe_y_cancela_deuda(): void
    {
        $cc = $this->cc(40301509.68, null, 100);
        $monto = 40301509.68;
        $imp = PagoproveedorAplicacionLadoSupport::importesAplicacionOp($cc, $monto);

        $this->assertEqualsWithDelta(-$monto, $imp['total_fila_pago'], 0.0001);
        $this->assertEqualsWithDelta(-$monto, $imp['apl_documento'], 0.0001);
        $this->assertEqualsWithDelta($monto, $imp['apl_fila_pago'], 0.0001);
        $this->assertEqualsWithDelta(
            0.0,
            ProveedorCuentacorrienteGrillaSupport::saldoPendiente($cc->total, $imp['apl_documento']),
            0.0001
        );
        $this->assertEqualsWithDelta(
            0.0,
            ProveedorCuentacorrienteGrillaSupport::saldoPendiente($imp['total_fila_pago'], $imp['apl_fila_pago']),
            0.0001
        );
    }

    public function test_nc_op_abre_haber_y_cancela_credito(): void
    {
        $cc = $this->cc(-79958.25, null, 200);
        $monto = 79958.25;
        $imp = PagoproveedorAplicacionLadoSupport::importesAplicacionOp($cc, $monto);

        $this->assertEqualsWithDelta($monto, $imp['total_fila_pago'], 0.0001);
        $this->assertEqualsWithDelta($monto, $imp['apl_documento'], 0.0001);
        $this->assertEqualsWithDelta(-$monto, $imp['apl_fila_pago'], 0.0001);
        $this->assertEqualsWithDelta(
            0.0,
            ProveedorCuentacorrienteGrillaSupport::saldoPendiente($cc->total, $imp['apl_documento']),
            0.0001
        );
        $this->assertEqualsWithDelta(
            0.0,
            ProveedorCuentacorrienteGrillaSupport::saldoPendiente($imp['total_fila_pago'], $imp['apl_fila_pago']),
            0.0001
        );
        $this->assertEqualsWithDelta(
            0.0,
            (float) $cc->total + $imp['total_fila_pago'],
            0.0001
        );
    }

    public function test_nc_aplicacion_negativa_duplica_credito_en_pendiente(): void
    {
        $this->assertEqualsWithDelta(
            -159916.50,
            ProveedorCuentacorrienteGrillaSupport::saldoPendiente(-79958.25, -79958.25),
            0.0001
        );
    }

    private function cc(float $total, ?int $pagoId, ?int $comprobanteId): Proveedor_Cuentacorriente
    {
        $cc = new Proveedor_Cuentacorriente;
        $cc->total = $total;
        $cc->pagoproveedor_id = $pagoId;
        $cc->comprobante_proveedor_id = $comprobanteId;

        return $cc;
    }

    private function ccConTipoPago(float $total, int $pagoId, string $tipocomprobante): Proveedor_Cuentacorriente
    {
        $cc = $this->cc($total, $pagoId, null);
        $pago = new \App\Models\Compras\Pagoproveedor;
        $pago->tipocomprobante = $tipocomprobante;
        $cc->setRelation('pagoproveedores', $pago);

        return $cc;
    }
}

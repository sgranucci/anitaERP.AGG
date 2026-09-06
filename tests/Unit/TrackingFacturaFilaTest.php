<?php

namespace Tests\Unit;

use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\Tracking\TrackingFacturaFila;
use App\Support\Compras\Tracking\TrackingPagoEstado;
use App\Support\Compras\Tracking\TrackingPdfReferencia;
use PHPUnit\Framework\TestCase;

class TrackingFacturaFilaTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $atributos
     */
    private function fila(array $atributos = []): TrackingFacturaFila
    {
        return TrackingFacturaFila::de((object) array_merge([
            'id' => 1,
            'letra' => 'A',
            'sucursal' => 2,
            'numerocomprobante' => 930,
            'estado' => ComprobanteProveedorEstados::CONTABILIZADO,
            'asiento_id' => 55,
            'codigoafiptipotransaccion_compra' => '01',
            'abreviaturatipotransaccion_compra' => 'FIB',
        ], $atributos));
    }

    public function test_arma_el_numero_con_el_formato_fiscal(): void
    {
        $this->assertSame('A 0002-00000930', $this->fila()->numero());
    }

    /**
     * Contabilizado es estado y asiento a la vez: si falta el asiento, el
     * comprobante todavía no llegó a la contabilidad aunque diga que sí.
     */
    public function test_sin_asiento_no_esta_contabilizado(): void
    {
        $this->assertTrue($this->fila()->contabilizado());
        $this->assertFalse($this->fila(['asiento_id' => null])->contabilizado());
        $this->assertSame(
            'Sin contabilizar',
            $this->fila(['asiento_id' => null])->estadoContable()['etiqueta']
        );
    }

    public function test_el_anulado_no_se_muestra_como_pendiente(): void
    {
        $estado = $this->fila([
            'estado' => ComprobanteProveedorEstados::ANULADO,
            'asiento_id' => null,
        ])->estadoContable();

        $this->assertSame('Anulado', $estado['etiqueta']);
    }

    /**
     * Un comprobante nunca indexado y uno indexado sin PDF son cosas
     * distintas: el primero está pendiente de resolver, el segundo es un
     * faltante real que hay que ir a escanear.
     */
    public function test_distingue_sin_resolver_de_falta_de_pdf(): void
    {
        $sinIndexar = $this->fila(['pdf_disponible' => false, 'sincronizado_at' => null]);
        $this->assertFalse($sinIndexar->tienePdf());
        $this->assertFalse($sinIndexar->indexado());

        $indexadoSinPdf = $this->fila(['pdf_disponible' => false, 'sincronizado_at' => '2026-09-05 10:00:00']);
        $this->assertFalse($indexadoSinPdf->tienePdf());
        $this->assertTrue($indexadoSinPdf->indexado());
    }

    public function test_muestra_el_origen_del_pdf(): void
    {
        $fila = $this->fila([
            'pdf_disponible' => true,
            'pdf_origen' => TrackingPdfReferencia::ORIGEN_ANITA,
        ]);

        $this->assertSame('Escaneo Anita', $fila->pdfOrigen());
    }

    /**
     * FIN/CIN no se escanean: el tracking les ofrece un PDF interno del ERP
     * aunque el índice diga que no hay archivo.
     */
    public function test_fin_sin_escaneo_puede_ver_pdf_interno(): void
    {
        $fila = $this->fila([
            'abreviaturatipotransaccion_compra' => 'FIN',
            'pdf_disponible' => false,
            'sincronizado_at' => '2026-09-05 10:00:00',
        ]);

        $this->assertFalse($fila->tienePdf());
        $this->assertTrue($fila->generaPdfInterno());
        $this->assertTrue($fila->puedeVerPdf());
        $this->assertSame('Interno ERP', $fila->pdfOrigen());
    }

    /**
     * La fecha de carga sin origen no se puede interpretar: en el histórico
     * importado el alta en el ERP es la fecha de la migración, no la de carga.
     */
    public function test_expone_el_origen_de_la_fecha_de_carga(): void
    {
        $fila = $this->fila([
            'fechacarga_efectiva' => '2025-06-27',
            'fechacarga_origen' => 'scan_anita',
        ]);

        $this->assertSame('27/06/2025', $fila->fechaCarga());
        $this->assertSame('Escaneo Anita', $fila->fechaCargaOrigen());
    }

    public function test_una_fila_sin_indice_no_afirma_nada_del_pago(): void
    {
        $this->assertSame('Sin resolver', $this->fila()->estadoPago()['etiqueta']);
    }

    public function test_traduce_el_estado_de_pago_del_indice(): void
    {
        $fila = $this->fila([
            'pago_estado' => TrackingPagoEstado::SIN_PAGAR,
            'pago_monto' => 183600.01,
            'pago_saldo' => 183600.01,
        ]);

        $this->assertSame('Sin pagar', $fila->estadoPago()['etiqueta']);
        $this->assertSame('tf-alerta', $fila->estadoPago()['clase']);
    }

    public function test_fechas_vacias_no_rompen_el_formateo(): void
    {
        $fila = $this->fila(['fechacomprobante' => null, 'pago_fecha' => '']);

        $this->assertSame('', $fila->fechaComprobante());
        $this->assertSame('', $fila->fechaPago());
    }

    /**
     * La fecha de contabilización sale del asiento, no del comprobante: el
     * comprobante puede tener fecha de agosto y haberse asentado en octubre.
     */
    public function test_toma_la_fecha_de_contabilizacion_del_asiento(): void
    {
        $fila = $this->fila([
            'fechacomprobante' => '2026-08-14',
            'fechacontabilizacion' => '2026-10-02',
            'numeroasiento' => 4712,
        ]);

        $this->assertSame('02/10/2026', $fila->fechaContabilizacion());
        $this->assertSame(4712, $fila->numeroAsiento());
    }

    public function test_sin_asiento_no_hay_fecha_de_contabilizacion(): void
    {
        $fila = $this->fila(['asiento_id' => null, 'fechacontabilizacion' => null]);

        $this->assertSame('', $fila->fechaContabilizacion());
        $this->assertSame(0, $fila->numeroAsiento());
    }

    public function test_expone_la_orden_de_compra_cuando_existe(): void
    {
        $fila = $this->fila(['ordencompra_id' => 88, 'numeroordencompra' => 1204]);

        $this->assertSame('1204', $fila->numeroOrdencompra());
        $this->assertSame(88, $fila->ordencompraId());
    }

    /**
     * La mayoría de las facturas de gasto entran sin orden de compra: eso no es
     * un dato faltante y la grilla no lo tiene que mostrar como tal.
     */
    public function test_la_factura_sin_orden_de_compra_no_inventa_numero(): void
    {
        $fila = $this->fila();

        $this->assertSame('', $fila->numeroOrdencompra());
        $this->assertSame(0, $fila->ordencompraId());
    }

    /**
     * La OP nativa del ERP trae id y por eso se puede enlazar; la importada del
     * Anita no tiene registro propio y sólo se muestra el número.
     */
    public function test_la_orden_de_pago_del_erp_se_puede_enlazar(): void
    {
        $fila = $this->fila([
            'pago_op_referencia' => 'OPP A 0003-00068386',
            'pago_op_cantidad' => 1,
            'pago_op_id' => 7,
        ]);

        $this->assertSame('OPP A 0003-00068386', $fila->ordenPago());
        $this->assertSame(7, $fila->ordenPagoId());
        $this->assertSame(0, $fila->ordenesPagoExtra());
    }

    public function test_la_orden_de_pago_importada_no_tiene_a_donde_enlazar(): void
    {
        $fila = $this->fila([
            'pago_op_referencia' => 'OPP A 0001-00113966',
            'pago_op_cantidad' => 1,
            'pago_op_id' => null,
        ]);

        $this->assertSame('OPP A 0001-00113966', $fila->ordenPago());
        $this->assertSame(0, $fila->ordenPagoId());
    }

    /**
     * Un comprobante en cuotas se cancela con varias OP y en la grilla entra
     * una sola: el resto se anuncia para que el dato no quede escondido.
     */
    public function test_anuncia_las_ordenes_de_pago_que_no_entran_en_la_grilla(): void
    {
        $fila = $this->fila(['pago_op_referencia' => 'OPP A 0001-00113966', 'pago_op_cantidad' => 3]);

        $this->assertSame(2, $fila->ordenesPagoExtra());
    }

    public function test_un_comprobante_sin_pagar_no_muestra_orden_de_pago(): void
    {
        $fila = $this->fila(['pago_estado' => TrackingPagoEstado::SIN_PAGAR]);

        $this->assertSame('', $fila->ordenPago());
        $this->assertSame(0, $fila->ordenesPagoExtra());
    }

    public function test_expone_la_antiguedad_cuando_hay_deuda(): void
    {
        $fila = $this->fila([
            'pago_estado' => TrackingPagoEstado::SIN_PAGAR,
            'pago_saldo' => 1000,
            'fechavencimiento' => (new \DateTimeImmutable('today'))->modify('-120 days')->format('Y-m-d'),
            'fechacomprobante' => (new \DateTimeImmutable('today'))->modify('-10 days')->format('Y-m-d'),
        ]);

        $antiguedad = $fila->antiguedadDeuda();
        $this->assertNotNull($antiguedad);
        $this->assertSame('90_mas', $antiguedad['tramo']);
        $this->assertSame('vencimiento', $antiguedad['origen']);
        $this->assertGreaterThan(90, $antiguedad['dias']);
    }

    public function test_antiguedad_a_vencer_cuando_el_vencimiento_es_futuro(): void
    {
        $fila = $this->fila([
            'pago_estado' => TrackingPagoEstado::SIN_PAGAR,
            'pago_saldo' => 500,
            'fechavencimiento' => (new \DateTimeImmutable('today'))->modify('+15 days')->format('Y-m-d'),
            'fechacomprobante' => (new \DateTimeImmutable('today'))->modify('-5 days')->format('Y-m-d'),
        ]);

        $antiguedad = $fila->antiguedadDeuda();
        $this->assertNotNull($antiguedad);
        $this->assertSame('corriente', $antiguedad['tramo']);
        $this->assertLessThan(0, $antiguedad['dias']);
    }

    public function test_un_comprobante_pagado_no_muestra_antiguedad(): void
    {
        $fila = $this->fila([
            'pago_estado' => TrackingPagoEstado::PAGADO,
            'pago_saldo' => 0,
            'fechacomprobante' => '2020-01-01',
        ]);

        $this->assertNull($fila->antiguedadDeuda());
    }
}

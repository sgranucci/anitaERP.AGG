<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\RecuentoMovimientosArticuloSupport;
use PHPUnit\Framework\TestCase;

class RecuentoMovimientosArticuloSupportTest extends TestCase
{
    public function test_cantidad_negativa_muestra_salida_aunque_tipo_sea_entrada(): void
    {
        $row = (object) [
            'cantidad' => -2.0,
            'tipo_abreviatura' => 'FAC',
            'tipo_nombre' => 'Factura',
        ];

        $enriquecida = RecuentoMovimientosArticuloSupport::enriquecerFila($row);

        $this->assertNull($enriquecida->entrada);
        $this->assertSame(2.0, $enriquecida->salida);
        $this->assertSame('2', $enriquecida->salida_fmt);
    }

    public function test_cantidad_positiva_muestra_entrada(): void
    {
        $row = (object) [
            'cantidad' => 3.0,
            'tipo_abreviatura' => 'NC',
            'tipo_nombre' => 'Nota de crédito',
        ];

        $enriquecida = RecuentoMovimientosArticuloSupport::enriquecerFila($row);

        $this->assertSame(3.0, $enriquecida->entrada);
        $this->assertNull($enriquecida->salida);
        $this->assertSame('3', $enriquecida->entrada_fmt);
    }

    public function test_anulacion_con_tipo_entrada_y_cantidad_negativa_va_a_salida(): void
    {
        $row = (object) [
            'cantidad' => -5.0,
            'tipo_abreviatura' => 'RCAJR',
            'tipo_nombre' => 'Recuento - Reverso anulación cierre',
        ];

        $enriquecida = RecuentoMovimientosArticuloSupport::enriquecerFila($row);

        $this->assertNull($enriquecida->entrada);
        $this->assertSame(5.0, $enriquecida->salida);
    }

    public function test_cantidad_cero_no_muestra_entrada_ni_salida(): void
    {
        $row = (object) [
            'cantidad' => 0.0,
            'tipo_abreviatura' => 'AJ',
            'tipo_nombre' => 'Ajuste',
        ];

        $enriquecida = RecuentoMovimientosArticuloSupport::enriquecerFila($row);

        $this->assertNull($enriquecida->entrada);
        $this->assertNull($enriquecida->salida);
        $this->assertSame('', $enriquecida->entrada_fmt);
        $this->assertSame('', $enriquecida->salida_fmt);
    }

    public function test_concepto_factura_usa_codigo_comprobante(): void
    {
        $row = (object) [
            'concepto' => 'Factura',
            'venta_codigo' => 'FAC B-00008-00807543',
            'venta_id' => 99,
            'tipo_venta_abreviatura' => 'FAC',
            'tipo_abreviatura' => 'Ing',
            'tipo_nombre' => 'Ingreso',
        ];

        $enriquecida = RecuentoMovimientosArticuloSupport::enriquecerFila($row);

        $this->assertSame('FAC B-00008-00807543', $enriquecida->concepto_display);
        $this->assertSame('FAC', $enriquecida->tipo);
    }

    public function test_concepto_insumo_formula_muestra_factura_y_sufijo(): void
    {
        $row = (object) [
            'concepto' => 'Factura — Ing.',
            'venta_codigo' => 'FAC B-00008-00807543',
            'venta_id' => 99,
        ];

        $concepto = RecuentoMovimientosArticuloSupport::resolverConceptoDisplay($row);

        $this->assertSame('FAC B-00008-00807543 - Insumo', $concepto);
    }

    public function test_concepto_insumo_nuevo_sufijo_en_grabacion(): void
    {
        $row = (object) [
            'concepto' => 'Factura - Insumo',
            'venta_codigo' => 'FAC B-00008-00807543',
            'venta_id' => 99,
        ];

        $concepto = RecuentoMovimientosArticuloSupport::resolverConceptoDisplay($row);

        $this->assertSame('FAC B-00008-00807543 - Insumo', $concepto);
    }

    public function test_modo_todos_depositos_se_detecta_con_cero(): void
    {
        $this->assertTrue(RecuentoMovimientosArticuloSupport::esModoTodosDepositos(0));
        $this->assertFalse(RecuentoMovimientosArticuloSupport::esModoTodosDepositos(5));
        $this->assertSame(0, RecuentoMovimientosArticuloSupport::resolverDepositoIdDesdeRequest('todos'));
        $this->assertSame(0, RecuentoMovimientosArticuloSupport::resolverDepositoIdDesdeRequest('0'));
        $this->assertSame(12, RecuentoMovimientosArticuloSupport::resolverDepositoIdDesdeRequest('12'));
    }

    public function test_enriquecer_fila_agrega_precio_unitario(): void
    {
        $row = (object) [
            'cantidad' => -1.0,
            'venta_id' => 5,
            'concepto' => 'Factura',
            'precio' => 100,
            'costo' => 0,
            'tipo_abreviatura' => 'FAC',
            'tipo_nombre' => 'Factura',
        ];

        $enriquecida = RecuentoMovimientosArticuloSupport::enriquecerFila($row);

        $this->assertSame(100.0, $enriquecida->precio_unitario);
        $this->assertSame('100', $enriquecida->precio_unitario_fmt);
    }
}

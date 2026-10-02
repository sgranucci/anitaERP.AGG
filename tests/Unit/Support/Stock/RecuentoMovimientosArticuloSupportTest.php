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

    public function test_saldo_parcial_ancla_el_cierre_al_saldo_vigente(): void
    {
        $filas = [
            (object) ['saldo_acumulado_movimientos' => 40],
            (object) ['saldo_acumulado_movimientos' => 113],
            (object) ['saldo_acumulado_movimientos' => 0],
        ];

        RecuentoMovimientosArticuloSupport::aplicarSaldoParcial($filas, 40, 40);

        $this->assertSame(40.0, $filas[0]->saldo_parcial);
        $this->assertSame('40', $filas[0]->saldo_parcial_fmt);
        $this->assertSame(113.0, $filas[1]->saldo_parcial);
        $this->assertSame(0.0, $filas[2]->saldo_parcial);
    }

    public function test_saldo_parcial_arrastra_diferencia_si_la_suma_no_cierra(): void
    {
        $filas = [
            (object) ['saldo_acumulado_movimientos' => 10],
        ];

        RecuentoMovimientosArticuloSupport::aplicarSaldoParcial($filas, 15, 10);

        $this->assertSame(15.0, $filas[0]->saldo_parcial);
    }

    public function test_cierre_total_sin_linea_aclara_que_no_esta_en_el_conteo(): void
    {
        $texto = RecuentoMovimientosArticuloSupport::aclaracionConceptoRecuento(
            'Recuento RC-000148 - cierre total',
            false
        );

        $this->assertStringContainsString('no está en el conteo', $texto);
        $this->assertStringContainsString('RC-000148', $texto);
    }

    public function test_cierre_total_ya_aclarado_no_se_duplica(): void
    {
        $original = 'Recuento RC-000148 - cierre total (no contado, saldo a cero)';

        $this->assertSame(
            $original,
            RecuentoMovimientosArticuloSupport::aclaracionConceptoRecuento($original, false)
        );
    }

    public function test_articulo_en_el_conteo_no_agrega_aclaracion(): void
    {
        $original = 'Recuento RC-000149 - cierre parcial';

        $this->assertSame(
            $original,
            RecuentoMovimientosArticuloSupport::aclaracionConceptoRecuento($original, true)
        );
    }

    public function test_anulacion_sin_linea_indica_que_no_figura_en_el_recuento(): void
    {
        $texto = RecuentoMovimientosArticuloSupport::aclaracionConceptoRecuento(
            'Anulación cierre recuento RC-000148',
            false
        );

        $this->assertStringContainsString('no figura en las líneas', $texto);
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

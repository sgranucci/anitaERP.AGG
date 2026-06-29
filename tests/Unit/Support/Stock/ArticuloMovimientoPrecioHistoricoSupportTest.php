<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\ArticuloMovimientoPrecioHistoricoSupport;
use PHPUnit\Framework\TestCase;

class ArticuloMovimientoPrecioHistoricoSupportTest extends TestCase
{
    public function test_venta_muestra_precio_unitario(): void
    {
        $row = (object) [
            'venta_id' => 10,
            'concepto' => 'Factura',
            'precio' => 1250.5,
            'costo' => 0,
        ];

        $this->assertTrue(ArticuloMovimientoPrecioHistoricoSupport::esMovimientoPrecioVenta($row));
        $this->assertSame(1250.5, ArticuloMovimientoPrecioHistoricoSupport::resolverUnitarioHistorico($row));

        $enriquecida = ArticuloMovimientoPrecioHistoricoSupport::enriquecerPrecioDisplay($row);
        $this->assertSame('1250.5', $enriquecida->precio_unitario_fmt);
        $this->assertSame(ArticuloMovimientoPrecioHistoricoSupport::ETIQUETA_PRECIO_VENTA, $enriquecida->precio_unitario_etiqueta);
    }

    public function test_insumo_con_venta_id_muestra_costo(): void
    {
        $row = (object) [
            'venta_id' => 10,
            'concepto' => 'Factura - Insumo',
            'precio' => 0,
            'costo' => 85.25,
        ];

        $this->assertFalse(ArticuloMovimientoPrecioHistoricoSupport::esMovimientoPrecioVenta($row));
        $this->assertSame(85.25, ArticuloMovimientoPrecioHistoricoSupport::resolverUnitarioHistorico($row));
        $this->assertSame(
            ArticuloMovimientoPrecioHistoricoSupport::ETIQUETA_COSTO_UNITARIO,
            ArticuloMovimientoPrecioHistoricoSupport::etiquetaPrecioUnitario($row)
        );
    }

    public function test_movimiento_manual_usa_costo_o_precio(): void
    {
        $conCosto = (object) ['venta_id' => null, 'concepto' => 'Recepción', 'precio' => 10, 'costo' => 20];
        $this->assertSame(20.0, ArticuloMovimientoPrecioHistoricoSupport::resolverUnitarioHistorico($conCosto));

        $soloPrecio = (object) ['venta_id' => null, 'concepto' => 'Ajuste', 'precio' => 15, 'costo' => 0];
        $this->assertSame(15.0, ArticuloMovimientoPrecioHistoricoSupport::resolverUnitarioHistorico($soloPrecio));
    }

    public function test_aplicar_precio_venta_deja_costo_en_cero(): void
    {
        $payload = ArticuloMovimientoPrecioHistoricoSupport::aplicarPrecioVenta(['costo' => 99], 500);

        $this->assertSame(500.0, $payload['precio']);
        $this->assertSame(0, $payload['costo']);
    }

    public function test_resolver_insumo_por_ids_vacio(): void
    {
        $this->assertSame([], ArticuloMovimientoPrecioHistoricoSupport::resolverUltimaCompraInsumoPorArticuloIds([]));
    }
}

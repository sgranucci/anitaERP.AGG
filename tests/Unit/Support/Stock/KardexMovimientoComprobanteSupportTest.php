<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\KardexMovimientoComprobanteSupport;
use PHPUnit\Framework\TestCase;

class KardexMovimientoComprobanteSupportTest extends TestCase
{
    protected function tearDown(): void
    {
        KardexMovimientoComprobanteSupport::resetCachePermisos();
        parent::tearDown();
    }

    public function test_query_consulta_abm_incluye_origen_y_vista(): void
    {
        $this->assertSame(
            ['origen' => 'modal_consulta', 'vista' => 'consulta'],
            KardexMovimientoComprobanteSupport::queryConsultaAbm()
        );
    }

    public function test_url_factura_null_sin_venta_id(): void
    {
        $this->assertNull(KardexMovimientoComprobanteSupport::urlFactura(0));
    }

    public function test_url_movimiento_stock_null_sin_id(): void
    {
        $this->assertNull(KardexMovimientoComprobanteSupport::urlMovimientoStock(0));
    }

    public function test_enriquecer_fila_agrega_urls_nulas_sin_ids(): void
    {
        $row = (object) [
            'cantidad' => 1.0,
            'tipo_abreviatura' => 'AJ',
            'tipo_nombre' => 'Ajuste',
        ];

        $enriquecida = KardexMovimientoComprobanteSupport::enriquecerFila($row);

        $this->assertNull($enriquecida->url_factura);
        $this->assertNull($enriquecida->url_movimientostock);
    }
}

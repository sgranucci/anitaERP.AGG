<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorCotizacionIngresoSupport;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorCotizacionIngresoSupportTest extends TestCase
{
    public function test_pesos_siempre_uno(): void
    {
        $r = ComprobanteProveedorCotizacionIngresoSupport::resolver(1, 1.51, 1510);

        $this->assertSame(1.0, $r['cotizacion']);
        $this->assertNull($r['marca_error']);
        $this->assertSame('mn', $r['origen']);
    }

    public function test_dolar_1_51_se_deduce_a_1510(): void
    {
        $r = ComprobanteProveedorCotizacionIngresoSupport::resolver(2, 1.51, 1510);

        $this->assertSame(1510.0, $r['cotizacion']);
        $this->assertSame(1.51, $r['cotizacion_recibida']);
        $this->assertSame(ComprobanteProveedorCotizacionIngresoSupport::MARCA_ESCALA, $r['marca_error']);
        $this->assertSame('deducida', $r['origen']);
        $this->assertNotNull($r['aviso']);
    }

    public function test_dolar_15_10_se_deduce_a_1510(): void
    {
        $r = ComprobanteProveedorCotizacionIngresoSupport::resolver(2, 15.10, 1510);

        $this->assertSame(1510.0, $r['cotizacion']);
        $this->assertSame(ComprobanteProveedorCotizacionIngresoSupport::MARCA_ESCALA, $r['marca_error']);
    }

    public function test_cotizacion_de_factura_cercana_al_dia_se_respeta(): void
    {
        $r = ComprobanteProveedorCotizacionIngresoSupport::resolver(2, 1480, 1510);

        $this->assertSame(1480.0, $r['cotizacion']);
        $this->assertNull($r['marca_error']);
        $this->assertSame('recibida', $r['origen']);
    }

    public function test_valor_ajeno_usa_la_del_dia_y_marca(): void
    {
        $r = ComprobanteProveedorCotizacionIngresoSupport::resolver(2, 42.7, 1510);

        $this->assertSame(1510.0, $r['cotizacion']);
        $this->assertSame(ComprobanteProveedorCotizacionIngresoSupport::MARCA_INVALIDA, $r['marca_error']);
        $this->assertSame('dia', $r['origen']);
    }

    public function test_uno_en_me_usa_la_del_dia(): void
    {
        $r = ComprobanteProveedorCotizacionIngresoSupport::resolver(2, 1, 1510);

        $this->assertSame(1510.0, $r['cotizacion']);
        $this->assertSame(ComprobanteProveedorCotizacionIngresoSupport::MARCA_INVALIDA, $r['marca_error']);
    }

    public function test_etiqueta_marca(): void
    {
        $this->assertSame(
            'Cotización escala',
            ComprobanteProveedorCotizacionIngresoSupport::etiquetaMarca(
                ComprobanteProveedorCotizacionIngresoSupport::MARCA_ESCALA
            )
        );
        $this->assertSame('', ComprobanteProveedorCotizacionIngresoSupport::etiquetaMarca(null));
    }
}

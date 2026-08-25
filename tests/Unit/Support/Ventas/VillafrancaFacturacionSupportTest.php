<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\PedidoFacturaAnitaArchivosSupport;
use App\Support\Ventas\VillafrancaFacturacionSupport;
use Tests\TestCase;

class VillafrancaFacturacionSupportTest extends TestCase
{
    public function test_es_reparto_101_por_tipoexpreso(): void
    {
        $pedido101 = (object) ['transportes' => (object) ['tipoexpreso' => '4']];
        $pedidoJunin = (object) ['transportes' => (object) ['tipoexpreso' => '3']];

        $this->assertTrue(VillafrancaFacturacionSupport::esReparto101($pedido101));
        $this->assertFalse(VillafrancaFacturacionSupport::esReparto101($pedidoJunin));
        $this->assertFalse(VillafrancaFacturacionSupport::esReparto101((object) []));
    }

    public function test_sucursal_numerador_propio_usa_config(): void
    {
        config()->set('facturacion.VILLAFRANCA_NUMERADOR_SUCURSAL', '1');

        $this->assertSame('1', VillafrancaFacturacionSupport::sucursalNumeradorPropio());
        $this->assertSame(PedidoFacturaAnitaArchivosSupport::PATH_VILLAFRANCA, VillafrancaFacturacionSupport::pathSistema());
    }

    public function test_vencimiento_villafranca_es_fecha_factura(): void
    {
        $cuotas = [
            ['fechavencimiento' => '2026-09-09', 'total' => 100],
            ['fechavencimiento' => '2026-09-20', 'total' => 50],
        ];

        $this->assertTrue(VillafrancaFacturacionSupport::debeForzarVencimientoFechaFactura(true));
        $this->assertSame(
            [
                ['fechavencimiento' => '2026-08-25', 'total' => 100],
                ['fechavencimiento' => '2026-08-25', 'total' => 50],
            ],
            VillafrancaFacturacionSupport::aplicarVencimientoFechaFactura($cuotas, '2026-08-25')
        );
    }

    public function test_vencimiento_por_punto_venta_division(): void
    {
        config()->set('facturacion.PUNTOVENTA_DIVISION_ID', 5);
        config()->set('facturacion.PUNTOVENTA_DIVISION_LOCAL_ID', 6);

        $this->assertTrue(VillafrancaFacturacionSupport::debeForzarVencimientoFechaFactura(
            false,
            (object) ['id' => 5]
        ));
        $this->assertFalse(VillafrancaFacturacionSupport::debeForzarVencimientoFechaFactura(
            false,
            (object) ['id' => 8]
        ));
    }
}

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

    public function test_punto_venta_reparto_101_usa_sucursal_1(): void
    {
        config()->set('facturacion.PUNTOVENTA_DIVISION_REPARTO_101_ID', 9);
        config()->set('facturacion.PUNTOVENTA_DIVISION_REPARTO_101_CODIGO', '00001');
        config()->set('facturacion.VILLAFRANCA_NUMERADOR_SUCURSAL', '1');

        $this->assertSame(9, VillafrancaFacturacionSupport::idPuntoVentaReparto101());
        $this->assertSame('00001', VillafrancaFacturacionSupport::codigoPuntoVentaReparto101());
        $this->assertTrue(VillafrancaFacturacionSupport::esPuntoVentaReparto101(9));
        $this->assertFalse(VillafrancaFacturacionSupport::esPuntoVentaReparto101(5));
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
        config()->set('facturacion.PUNTOVENTA_DIVISION_REPARTO_101_ID', 9);

        $this->assertTrue(VillafrancaFacturacionSupport::debeForzarVencimientoFechaFactura(
            false,
            (object) ['id' => 5]
        ));
        $this->assertTrue(VillafrancaFacturacionSupport::debeForzarVencimientoFechaFactura(
            false,
            (object) ['id' => 9]
        ));
        $this->assertFalse(VillafrancaFacturacionSupport::debeForzarVencimientoFechaFactura(
            false,
            (object) ['id' => 8]
        ));
    }

    public function test_referencia_pendmae_usa_sucursal_de_emision(): void
    {
        $ref = VillafrancaFacturacionSupport::referenciaPendmaeDesdeFactura('FAC', 'A', '00015', 145761);

        $this->assertSame('FAC', $ref['tipo']);
        $this->assertSame('A', $ref['letra']);
        $this->assertSame(15, $ref['sucursal']);
        $this->assertSame(145761, $ref['nro']);

        $data = VillafrancaFacturacionSupport::aplicarReferenciaPendmae([], $ref);
        $this->assertSame('FAC', $data['penm_ref_tipo']);
        $this->assertSame('A', $data['penm_ref_letra']);
        $this->assertSame(15, $data['penm_ref_sucursal']);
        $this->assertSame(145761, $data['penm_ref_nro']);
    }

    public function test_referencia_pendmae_desde_request_vacia_queda_en_blanco(): void
    {
        $ref = VillafrancaFacturacionSupport::referenciaPendmaeDesdeRequest([]);

        $this->assertSame(' ', $ref['tipo']);
        $this->assertSame(' ', $ref['letra']);
        $this->assertSame(0, $ref['sucursal']);
        $this->assertSame(0, $ref['nro']);
    }

    public function test_partir_monto_factura_reparto_101_como_anita(): void
    {
        config()->set('facturacion.COEFICIENTE_EXTRA_REPARTO_101', 1.10);

        $montos = VillafrancaFacturacionSupport::partirMontoFactura(1100.00);

        $this->assertSame(1000.00, $montos['neto']);
        $this->assertSame(100.00, $montos['recargo']);
        $this->assertSame(1100.00, $montos['total']);
        $this->assertSame(1.10, $montos['coeficiente']);
    }

    public function test_montos_pedido_solo_con_factura_villafranca_101(): void
    {
        config()->set('facturacion.COEFICIENTE_EXTRA_REPARTO_101', 1.10);
        config()->set('facturacion.PUNTOVENTA_DIVISION_ID', 5);
        config()->set('facturacion.PUNTOVENTA_DIVISION_LOCAL_ID', 6);
        config()->set('facturacion.PUNTOVENTA_DIVISION_REPARTO_101_ID', 9);

        $pedido101 = (object) [
            'id' => 10,
            'transportes' => (object) ['tipoexpreso' => '4'],
            'ventas' => collect([
                (object) ['id' => 1, 'puntoventa_id' => 2, 'total' => 999],
                (object) ['id' => 2, 'puntoventa_id' => 5, 'total' => 1100],
            ]),
        ];

        $pedidoSinFactura = (object) [
            'id' => 11,
            'transportes' => (object) ['tipoexpreso' => '4'],
            'ventas' => collect([
                (object) ['id' => 3, 'puntoventa_id' => 2, 'total' => 500],
            ]),
        ];
        $pedidoJunin = (object) [
            'id' => 12,
            'transportes' => (object) ['tipoexpreso' => '3'],
            'ventas' => collect([
                (object) ['id' => 4, 'puntoventa_id' => 5, 'total' => 1100],
            ]),
        ];

        $this->assertSame(
            ['neto' => 1000.00, 'recargo' => 100.00, 'total' => 1100.00, 'coeficiente' => 1.10],
            VillafrancaFacturacionSupport::montosPedidoDesdeFactura($this->pedidoConVentasCargadas($pedido101))
        );
        $this->assertNull(VillafrancaFacturacionSupport::montosPedidoDesdeFactura(
            $this->pedidoConVentasCargadas($pedidoSinFactura)
        ));
        $this->assertNull(VillafrancaFacturacionSupport::montosPedidoDesdeFactura(
            $this->pedidoConVentasCargadas($pedidoJunin)
        ));
    }

    private function pedidoConVentasCargadas(object $pedido): object
    {
        return new class($pedido) {
            public $id;
            public $transportes;
            public $ventas;

            public function __construct(object $pedido)
            {
                $this->id = $pedido->id;
                $this->transportes = $pedido->transportes;
                $this->ventas = $pedido->ventas;
            }

            public function relationLoaded($relation): bool
            {
                return $relation === 'ventas';
            }
        };
    }

    public function test_venta_origen_solo_en_punto_venta_division(): void
    {
        config()->set('facturacion.PUNTOVENTA_DIVISION_ID', 5);
        config()->set('facturacion.PUNTOVENTA_DIVISION_LOCAL_ID', 6);
        config()->set('facturacion.PUNTOVENTA_DIVISION_REPARTO_101_ID', 9);

        $this->assertSame(314, VillafrancaFacturacionSupport::ventaOrigenIdParaGrabar(5, 314));
        $this->assertSame(314, VillafrancaFacturacionSupport::ventaOrigenIdParaGrabar(9, 314));
        $this->assertNull(VillafrancaFacturacionSupport::ventaOrigenIdParaGrabar(8, 314));
        $this->assertNull(VillafrancaFacturacionSupport::ventaOrigenIdParaGrabar(5, 0));
    }

    public function test_nc_hereda_origen_de_la_factura_aplicada(): void
    {
        config()->set('facturacion.PUNTOVENTA_DIVISION_ID', 5);
        config()->set('facturacion.PUNTOVENTA_DIVISION_LOCAL_ID', 6);
        config()->set('facturacion.PUNTOVENTA_DIVISION_REPARTO_101_ID', 9);

        $vfConOrigen = (object) ['id' => 315, 'puntoventa_id' => 5, 'venta_origen_id' => 314];
        $facBierzo = (object) ['id' => 314, 'puntoventa_id' => 8, 'venta_origen_id' => null];
        $vfSinOrigen = (object) ['id' => 320, 'puntoventa_id' => 5, 'venta_origen_id' => null];

        $this->assertSame(314, VillafrancaFacturacionSupport::heredarOrigenIdDesdeVenta($vfConOrigen));
        $this->assertSame(314, VillafrancaFacturacionSupport::heredarOrigenIdDesdeVenta($facBierzo));
        $this->assertNull(VillafrancaFacturacionSupport::heredarOrigenIdDesdeVenta($vfSinOrigen));
        $this->assertNull(VillafrancaFacturacionSupport::heredarOrigenIdDesdeVenta(null));
    }
}

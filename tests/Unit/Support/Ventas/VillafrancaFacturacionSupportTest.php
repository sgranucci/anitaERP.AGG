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
}

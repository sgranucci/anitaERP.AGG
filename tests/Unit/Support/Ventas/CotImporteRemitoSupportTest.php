<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\CotImporteRemitoSupport;
use PHPUnit\Framework\TestCase;

class CotImporteRemitoSupportTest extends TestCase
{
    public function test_detecta_importe_de_relleno_de_comprob(): void
    {
        $this->assertTrue(CotImporteRemitoSupport::esPlaceholder(0.0));
        $this->assertTrue(CotImporteRemitoSupport::esPlaceholder(1.0));
        $this->assertFalse(CotImporteRemitoSupport::esPlaceholder(1.01));
        $this->assertFalse(CotImporteRemitoSupport::esPlaceholder(1348470.78));
    }

    public function test_usa_venta_anita_y_descarta_exento_uno_de_comprob(): void
    {
        $venta = (object) [
            'ven_gravado' => 1348470.78,
            'ven_gravado_ot' => 0,
            'ven_exento' => 0,
        ];
        $comprob = (object) [
            'comp_gravado' => 0,
            'comp_exento' => 1.0,
            'comp_total' => 0,
            'comp_iva' => 0,
        ];

        $this->assertSame(1.0, CotImporteRemitoSupport::desdeAnitaComprob($comprob));
        $this->assertSame(1348470.78, CotImporteRemitoSupport::desdeAnitaVenta($venta));
        $this->assertSame(
            1348470.78,
            CotImporteRemitoSupport::preferir(
                CotImporteRemitoSupport::desdeAnitaVenta($venta),
                CotImporteRemitoSupport::desdeAnitaComprob($comprob),
            )
        );
    }

    public function test_no_valida_para_cot_el_relleno_ni_cero(): void
    {
        $this->assertFalse(CotImporteRemitoSupport::esValidoParaCot(1.0));
        $this->assertFalse(CotImporteRemitoSupport::esValidoParaCot(0.0));
        $this->assertTrue(CotImporteRemitoSupport::esValidoParaCot(1348470.78));
        $this->assertNull(CotImporteRemitoSupport::primeroValido(0.0, 1.0));
        $this->assertSame(250.5, CotImporteRemitoSupport::primeroValido(1.0, 250.5));
    }

    public function test_desglose_erp_suma_gravado_exento_y_no_gravado(): void
    {
        $this->assertSame(150.0, CotImporteRemitoSupport::desdeDesgloseErp([
            'neto_gravado' => 100,
            'exento' => 40,
            'no_gravado' => 10,
        ]));
        $this->assertSame(0.0, CotImporteRemitoSupport::desdeDesgloseErp(null));
    }

    public function test_aplicar_a_fila_acepta_factura_o_neto_de_remito(): void
    {
        $ok = CotImporteRemitoSupport::aplicarAFila(['importe' => 1], 1348.5, 'factura_anita');
        $this->assertTrue($ok['importe_ok']);
        $this->assertTrue($ok['importe_desde_factura']);
        $this->assertSame(1348.5, $ok['importe']);

        $sinFactura = CotImporteRemitoSupport::aplicarAFila(['importe' => 0], 361268.98, 'remito_anita');
        $this->assertTrue($sinFactura['importe_ok']);
        $this->assertFalse($sinFactura['importe_desde_factura']);
        $this->assertSame('Remito Anita (sin factura)', $sinFactura['importe_origen_etiqueta']);

        $malo = CotImporteRemitoSupport::aplicarAFila(['importe' => 1], 1.0);
        $this->assertFalse($malo['importe_ok']);
        $this->assertTrue($malo['importe_placeholder']);
        $this->assertNotEmpty($malo['importe_motivo']);
    }

    public function test_resolver_prioriza_factura_anita_sobre_neto_de_remito(): void
    {
        $resuelto = CotImporteRemitoSupport::resolver([
            'factura_anita' => 1348470.78,
            'remito_anita' => 361268.98,
        ]);
        $this->assertSame(1348470.78, $resuelto['importe']);
        $this->assertSame('factura_anita', $resuelto['origen']);

        $sinFactura = CotImporteRemitoSupport::resolver([
            'factura_anita' => 1.0,
            'remito_anita' => 361268.98,
        ]);
        $this->assertSame(361268.98, $sinFactura['importe']);
        $this->assertSame('remito_anita', $sinFactura['origen']);
    }

    public function test_neto_pendmae_usa_seguro_o_neto_y_descarta_uno(): void
    {
        $this->assertSame(361268.98, CotImporteRemitoSupport::desdeAnitaPendmae((object) [
            'penm_tot_seguro' => 361268.98,
            'penm_neto' => 361268.98,
        ]));
        $this->assertSame(5000.0, CotImporteRemitoSupport::desdeAnitaPendmae((object) [
            'penm_tot_seguro' => 1,
            'penm_neto' => 5000,
        ]));
        $this->assertSame(0.0, CotImporteRemitoSupport::desdeAnitaPendmae((object) [
            'penm_tot_seguro' => 1,
            'penm_neto' => 0,
        ]));
    }
}

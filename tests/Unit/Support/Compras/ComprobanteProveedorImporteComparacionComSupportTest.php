<?php

namespace Tests\Unit\Support\Compras;

use App\Services\Compras\ComprobanteProveedorAsientoService;
use App\Support\Compras\ComprobanteProveedorAsientoPreviewSupport;
use App\Support\Compras\ComprobanteProveedorImporteComparacionComSupport;
use App\Support\Compras\ComprobanteProveedorToleranciaImporteSupport;
use ReflectionClass;
use Tests\TestCase;

class ComprobanteProveedorImporteComparacionComSupportTest extends TestCase
{
    public function test_letra_a_no_suma_ii_si_la_com_no_lo_provisiono(): void
    {
        $conceptos = [
            $this->linea('G', 823635.59, '50'),
            $this->linea('N', 83355.91, '5'),
            $this->linea('I', 172963.47, '503'),
        ];

        $meta = ComprobanteProveedorImporteComparacionComSupport::importeParaCompararConRecepcion(
            'A',
            1,
            1079954.97,
            823635.59,
            $conceptos,
        );

        $this->assertSame(823635.59, $meta['importe']);
        $this->assertSame('gravado', $meta['tipo']);
        $this->assertFalse(
            ComprobanteProveedorToleranciaImporteSupport::excedeTolerancia(823635.59, 823635.59, 5.0)
        );
        $this->assertTrue(
            ComprobanteProveedorToleranciaImporteSupport::excedeTolerancia(906991.50, 823635.59, 5.0),
            'Si se suma el II tipo N (código 5) contra una COM sin II, YAFEMA FGA queda fuera de tolerancia.'
        );
    }

    public function test_letra_a_incluye_ii_solo_cuando_la_com_lo_provisiono(): void
    {
        $conceptos = [
            $this->linea('G', 1000.00, '50'),
            $this->linea('T', 400.00, '510'),
            $this->linea('I', 210.00, '503'),
        ];

        $meta = ComprobanteProveedorImporteComparacionComSupport::importeParaCompararConRecepcion(
            'A',
            1,
            1610.00,
            1000.00,
            $conceptos,
            true,
        );

        $this->assertSame(1400.00, $meta['importe']);
        $this->assertSame('gravado_mas_ii', $meta['tipo']);
        $this->assertStringContainsString('impuesto interno', $meta['etiqueta']);
        $this->assertFalse(
            ComprobanteProveedorToleranciaImporteSupport::excedeTolerancia(1400.00, 1400.00, 5.0)
        );
    }

    public function test_letra_a_sin_ii_sigue_usando_solo_neto_gravado(): void
    {
        $conceptos = [
            $this->linea('G', 1000.00, '50'),
            $this->linea('I', 210.00, '503'),
        ];

        $meta = ComprobanteProveedorImporteComparacionComSupport::importeParaCompararConRecepcion(
            'A',
            1,
            1210.00,
            1000.00,
            $conceptos,
        );

        $this->assertSame(1000.00, $meta['importe']);
        $this->assertSame('gravado', $meta['tipo']);
        $this->assertSame('neto gravado (letra A)', $meta['etiqueta']);
    }

    public function test_letra_a_no_suma_no_gravado_que_no_esta_en_el_total(): void
    {
        // FGA A 00004-00079313 (Aquarine, OC 223977): el «No gravado» repite el IVA.
        // El neto de la factura y la provisión COM son el subtotal.
        $conceptos = [
            $this->linea('E', 70266.09, '1'),
            $this->linea('I', 67305.80, '503'),
            $this->linea('I', 2960.29, '504'),
            $this->linea('B', 20921.82, '140'),
            $this->linea('G', 320503.81, '50'),
            $this->linea('G', 28193.24, '6'),
        ];

        $meta = ComprobanteProveedorImporteComparacionComSupport::importeParaCompararConRecepcion(
            'A',
            1,
            439885.02,
            348697.11,
            $conceptos,
        );

        $this->assertSame(348697.11, $meta['importe']);
        $this->assertSame('gravado', $meta['tipo']);
        $this->assertFalse(
            ComprobanteProveedorToleranciaImporteSupport::excedeTolerancia(348697.11, 348697.11, 5.0)
        );
    }

    public function test_letra_a_el_exento_real_sigue_en_el_neto_si_el_total_lo_incluye(): void
    {
        $conceptos = [
            $this->linea('G', 1000.00, '50'),
            $this->linea('E', 200.00, '1'),
            $this->linea('I', 210.00, '503'),
        ];

        $meta = ComprobanteProveedorImporteComparacionComSupport::importeParaCompararConRecepcion(
            'A',
            1,
            1410.00,
            1200.00,
            $conceptos,
        );

        $this->assertSame(1200.00, $meta['importe']);
    }

    public function test_letra_b_compara_el_total(): void
    {
        $meta = ComprobanteProveedorImporteComparacionComSupport::importeParaCompararConRecepcion(
            'B',
            1,
            1610.00,
            1000.00,
            [$this->linea('G', 1000.00), $this->linea('T', 400.00)],
        );

        $this->assertSame(1610.00, $meta['importe']);
        $this->assertSame('total', $meta['tipo']);
    }

    public function test_provision_incluye_ii_segun_campo_de_recepcion(): void
    {
        $this->assertFalse(
            ComprobanteProveedorImporteComparacionComSupport::provisionIncluyeImpuestoInterno([
                (object) ['impuesto_interno' => 0],
            ])
        );
        $this->assertTrue(
            ComprobanteProveedorImporteComparacionComSupport::provisionIncluyeImpuestoInterno([
                (object) ['impuesto_interno' => 1500.25],
            ])
        );
    }

    public function test_asiento_contra_com_usa_flag_de_ii_en_la_provision(): void
    {
        $src = (string) file_get_contents(
            (new ReflectionClass(ComprobanteProveedorAsientoService::class))->getFileName()
        );
        $this->assertStringContainsString(
            'ComprobanteProveedorConceptoIvaTipos::revierteProvisionCom(',
            $src
        );
        $this->assertStringContainsString('$comIncluyeIi', $src);
        $preview = (string) file_get_contents(
            (new ReflectionClass(ComprobanteProveedorAsientoPreviewSupport::class))->getFileName()
        );
        $this->assertStringContainsString(
            'ComprobanteProveedorConceptoIvaTipos::revierteProvisionCom(',
            $preview
        );
    }

    private function linea(string $tipo, float $monto, string $codigo = ''): object
    {
        return (object) [
            'monto' => $monto,
            'concepto_ivacompras' => (object) [
                'tipoconcepto' => $tipo,
                'codigo' => $codigo,
            ],
        ];
    }
}

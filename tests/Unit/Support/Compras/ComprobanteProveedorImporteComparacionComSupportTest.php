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
    public function test_letra_a_incluye_impuesto_interno_en_el_comparable_contra_com(): void
    {
        $conceptos = [
            $this->linea('G', 1000.00),
            $this->linea('T', 400.00),
            $this->linea('I', 210.00),
        ];

        $meta = ComprobanteProveedorImporteComparacionComSupport::importeParaCompararConRecepcion(
            'A',
            1,
            1610.00,
            1000.00,
            $conceptos,
        );

        $this->assertSame(1400.00, $meta['importe']);
        $this->assertSame('gravado_mas_ii', $meta['tipo']);
        $this->assertStringContainsString('impuesto interno', $meta['etiqueta']);
        $this->assertFalse(
            ComprobanteProveedorToleranciaImporteSupport::excedeTolerancia(1400.00, 1400.00, 5.0)
        );
        $this->assertTrue(
            ComprobanteProveedorToleranciaImporteSupport::excedeTolerancia(1000.00, 1400.00, 5.0),
            'Sin el II el comparable viejo (solo neto) queda fuera de tolerancia, caso YAFEMA.'
        );
    }

    public function test_letra_a_sin_ii_sigue_usando_solo_neto_gravado(): void
    {
        $conceptos = [
            $this->linea('G', 1000.00),
            $this->linea('I', 210.00),
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

    public function test_asiento_contra_com_acumula_ii_en_la_provision(): void
    {
        $src = (string) file_get_contents(
            (new ReflectionClass(ComprobanteProveedorAsientoService::class))->getFileName()
        );
        $this->assertStringContainsString(
            'ComprobanteProveedorConceptoIvaTipos::revierteProvisionCom($tipoConcepto)',
            $src
        );
        $preview = (string) file_get_contents(
            (new ReflectionClass(ComprobanteProveedorAsientoPreviewSupport::class))->getFileName()
        );
        $this->assertStringContainsString(
            'ComprobanteProveedorConceptoIvaTipos::revierteProvisionCom($tipoConcepto)',
            $preview
        );
    }

    private function linea(string $tipo, float $monto): object
    {
        return (object) [
            'monto' => $monto,
            'concepto_ivacompras' => (object) ['tipoconcepto' => $tipo],
        ];
    }
}

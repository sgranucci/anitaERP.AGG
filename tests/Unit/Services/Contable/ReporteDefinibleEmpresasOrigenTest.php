<?php

namespace Tests\Unit\Services\Contable;

use App\Services\Contable\ReporteDefinibleParidadService;
use Tests\TestCase;

class ReporteDefinibleEmpresasOrigenTest extends TestCase
{
    public function test_el_rotulo_nombra_una_o_varias_empresas(): void
    {
        $this->assertSame('', ReporteDefinibleParidadService::textoDesdeNombres([]));
        $this->assertSame('', ReporteDefinibleParidadService::textoDesdeNombres(['', '  ']));
        $this->assertSame(
            'Empresa: REBISCO S.A.',
            ReporteDefinibleParidadService::textoDesdeNombres([' REBISCO S.A. '])
        );
        $this->assertSame(
            'Empresas: REBISCO S.A., VARELA SLOTS',
            ReporteDefinibleParidadService::textoDesdeNombres(['REBISCO S.A.', 'VARELA SLOTS'])
        );
    }

    public function test_el_excel_y_el_pdf_imprimen_la_empresa_de_origen(): void
    {
        $reporte = new \App\Models\Contable\ReporteContable;
        $reporte->nombre = 'To compare with Budget';
        $reporte->titulo1 = 'To compare with Budget';
        $reporte->titulo2 = '';

        $excel = view('exports.contable.reporte_definibleindex', [
            'reporte' => $reporte,
            'resultado' => ['columnas' => [], 'filas' => [], 'criterios' => ''],
            'hayFilaLogos' => false,
            'empresas_origen' => 'Empresa: REBISCO S.A.',
            'excel_formato_numero' => 'ar',
        ])->render();

        $this->assertStringContainsString('Empresa: REBISCO S.A.', $excel);

        $pdf = view('contable.reporte_definible.listado', [
            'reporte' => $reporte,
            'resultado' => ['columnas' => [], 'filas' => [], 'notas' => []],
            'filtros' => ['empresa_ids' => []],
            'periodo_texto' => '09/2026 — 10/2026',
        ])->render();

        $this->assertStringNotContainsString('Empresa:', $pdf);
    }
}

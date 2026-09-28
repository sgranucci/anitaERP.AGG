<?php

namespace Tests\Unit\Support\Contable;

use App\Support\Contable\AsientoCentrocostoObligatorioSupport;
use PHPUnit\Framework\TestCase;

class AsientoCentrocostoObligatorioSupportTest extends TestCase
{
    public function test_exige_centro_cuando_la_cuenta_lo_maneja(): void
    {
        $etiquetas = AsientoCentrocostoObligatorioSupport::etiquetasSinCentrocosto([
            'cuentacontable_ids' => [10, 20],
            'centrocosto_ids' => [0, 5],
            'debes' => ['100,00', '0'],
            'haberes' => ['0', '100,00'],
        ], [
            10 => ['codigo' => '51101', 'nombre' => 'Gastos', 'manejaccosto' => 'S'],
            20 => ['codigo' => '11101', 'nombre' => 'Caja', 'manejaccosto' => 'N'],
        ]);

        $this->assertSame(['Línea 1: 51101 — Gastos'], $etiquetas);
    }

    public function test_no_exige_centro_si_la_cuenta_no_lo_maneja(): void
    {
        $etiquetas = AsientoCentrocostoObligatorioSupport::etiquetasSinCentrocosto([
            'cuentacontable_ids' => [10],
            'centrocosto_ids' => [0],
            'debes' => [50],
            'haberes' => [0],
        ], [
            10 => ['codigo' => '11101', 'nombre' => 'Caja', 'manejaccosto' => 'N'],
        ]);

        $this->assertSame([], $etiquetas);
    }

    public function test_acepta_centro_previo_si_el_select_no_viaja(): void
    {
        $etiquetas = AsientoCentrocostoObligatorioSupport::etiquetasSinCentrocosto([
            'cuentacontable_ids' => [10],
            'centrocosto_id_previo' => [8],
            'debes' => [50],
            'haberes' => [0],
        ], [
            10 => ['codigo' => '51101', 'nombre' => 'Gastos', 'manejaccosto' => '1'],
        ]);

        $this->assertSame([], $etiquetas);
    }

    public function test_omite_lineas_sin_importe(): void
    {
        $etiquetas = AsientoCentrocostoObligatorioSupport::etiquetasSinCentrocosto([
            'cuentacontable_ids' => [10, ''],
            'centrocosto_ids' => [0, 0],
            'debes' => [0, 0],
            'haberes' => ['', 0],
        ], [
            10 => ['codigo' => '51101', 'nombre' => 'Gastos', 'manejaccosto' => 'S'],
        ]);

        $this->assertSame([], $etiquetas);
    }
}

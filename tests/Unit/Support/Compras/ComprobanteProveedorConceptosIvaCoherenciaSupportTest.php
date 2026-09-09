<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorConceptosIvaCoherenciaSupport;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorConceptosIvaCoherenciaSupportTest extends TestCase
{
    /**
     * Caso OC 223204: agente mandó −1200 en No gravado; sin esa línea el total cierra.
     */
    public function test_descarta_descuento_negativo_si_total_ya_cuadra(): void
    {
        $lineas = [
            ['concepto_ivacompra_id' => 1, 'monto' => -1200.0],
            ['concepto_ivacompra_id' => 50, 'monto' => 22800.0],
            ['concepto_ivacompra_id' => 503, 'monto' => 4788.0],
            ['concepto_ivacompra_id' => 141, 'monto' => 2.28],
        ];
        $total = 27590.28;

        $resultado = ComprobanteProveedorConceptosIvaCoherenciaSupport::descartarLineasNegativasSiTotalYaCuadra(
            $lineas,
            $total
        );

        $this->assertTrue($resultado['descartó']);
        $this->assertCount(1, $resultado['descartadas']);
        $this->assertSame(-1200.0, $resultado['descartadas'][0]['monto']);
        $this->assertCount(3, $resultado['lineas']);

        $cuadre = ComprobanteProveedorConceptosIvaCoherenciaSupport::cuadreConTotal(
            $resultado['lineas'],
            $total
        );
        $this->assertTrue($cuadre['cuadra']);
    }

    public function test_conserva_negativo_si_hace_falta_para_cuadrar(): void
    {
        $lineas = [
            ['concepto_ivacompra_id' => 50, 'monto' => 10000.0],
            ['concepto_ivacompra_id' => 80, 'monto' => -500.0],
            ['concepto_ivacompra_id' => 503, 'monto' => 1995.0],
        ];
        $total = 11495.0;

        $resultado = ComprobanteProveedorConceptosIvaCoherenciaSupport::descartarLineasNegativasSiTotalYaCuadra(
            $lineas,
            $total
        );

        $this->assertFalse($resultado['descartó']);
        $this->assertCount(3, $resultado['lineas']);
        $this->assertSame([], $resultado['descartadas']);
    }

    public function test_no_toca_nc_con_todos_los_conceptos_negativos(): void
    {
        $lineas = [
            ['concepto_ivacompra_id' => 50, 'monto' => -1000.0],
            ['concepto_ivacompra_id' => 503, 'monto' => -210.0],
        ];

        $resultado = ComprobanteProveedorConceptosIvaCoherenciaSupport::descartarLineasNegativasSiTotalYaCuadra(
            $lineas,
            1210.0
        );

        $this->assertFalse($resultado['descartó']);
        $this->assertCount(2, $resultado['lineas']);
    }

    public function test_acepta_campo_importe_para_pipeline_pdf_ia(): void
    {
        $lineas = [
            ['id_concepto' => '1', 'importe' => -1200.0],
            ['id_concepto' => '50', 'importe' => 22800.0],
            ['id_concepto' => '503', 'importe' => 4788.0],
            ['id_concepto' => '141', 'importe' => 2.28],
        ];

        $resultado = ComprobanteProveedorConceptosIvaCoherenciaSupport::descartarLineasNegativasSiTotalYaCuadra(
            $lineas,
            27590.28,
            ComprobanteProveedorConceptosIvaCoherenciaSupport::TOLERANCIA,
            'importe'
        );

        $this->assertTrue($resultado['descartó']);
        $this->assertCount(3, $resultado['lineas']);
    }
}

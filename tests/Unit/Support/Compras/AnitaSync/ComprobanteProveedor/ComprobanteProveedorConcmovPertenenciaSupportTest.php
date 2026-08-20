<?php

namespace Tests\Unit\Support\Compras\AnitaSync\ComprobanteProveedor;

use App\Support\Compras\AnitaSync\ComprobanteProveedor\ComprobanteProveedorConcmovPertenenciaSupport;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorConcmovPertenenciaSupportTest extends TestCase
{
    public function test_separa_lineas_de_fnb_869_y_deja_las_de_zaphira(): void
    {
        $erp = [
            ['concepto' => 50, 'importe' => 6709.69],
            ['concepto' => 411, 'importe' => 1409.03],
            ['concepto' => 140, 'importe' => 20.13],
            ['concepto' => 141, 'importe' => 0.67],
        ];
        $concmov = [
            ['concepto' => 50, 'importe' => 6709.69],
            ['concepto' => 50, 'importe' => 2347150.0],
            ['concepto' => 140, 'importe' => 20.13],
            ['concepto' => 141, 'importe' => 0.67],
            ['concepto' => 411, 'importe' => 1409.03],
            ['concepto' => 503, 'importe' => 492901.5],
        ];

        $part = ComprobanteProveedorConcmovPertenenciaSupport::particionar($erp, $concmov);

        $this->assertTrue($part['ok']);
        $this->assertCount(4, $part['de_erp']);
        $this->assertCount(2, $part['de_otras']);
        $this->assertSame([], $part['erp_sin_concmov']);

        $otrasConceptos = array_column($part['de_otras'], 'concepto');
        sort($otrasConceptos);
        $this->assertSame([50, 503], $otrasConceptos);

        $importeCincuentaOtra = null;
        foreach ($part['de_otras'] as $linea) {
            if ((int) $linea['concepto'] === 50) {
                $importeCincuentaOtra = (float) $linea['importe'];
            }
        }
        $this->assertEqualsWithDelta(2347150.0, $importeCincuentaOtra, 0.02);
    }

    public function test_ambiguo_si_dos_lineas_tienen_mismo_concepto_e_importe(): void
    {
        $erp = [
            ['concepto' => 50, 'importe' => 100.0],
        ];
        $concmov = [
            ['concepto' => 50, 'importe' => 100.0],
            ['concepto' => 50, 'importe' => 100.01],
        ];

        $part = ComprobanteProveedorConcmovPertenenciaSupport::particionar($erp, $concmov);

        $this->assertFalse($part['ok']);
        $this->assertStringContainsString('Ambiguo', (string) $part['error']);
        $this->assertSame([], $part['de_erp']);
        $this->assertSame([], $part['de_otras']);
    }

    public function test_where_borrar_incluye_interno_concepto_e_importe(): void
    {
        $where = ComprobanteProveedorConcmovPertenenciaSupport::whereBorrarLinea(427704, 50, 6709.69);

        $this->assertStringContainsString("concv_nro_interno = '427704'", $where);
        $this->assertStringContainsString("concv_concepto = '50'", $where);
        $this->assertStringContainsString("concv_importe = '6709.69'", $where);
    }

    public function test_siguiente_interno_usa_el_mayor_piso(): void
    {
        $this->assertSame(
            427800,
            ComprobanteProveedorConcmovPertenenciaSupport::calcularSiguienteInterno(427799, 427704, 427799)
        );
        $this->assertSame(
            10,
            ComprobanteProveedorConcmovPertenenciaSupport::calcularSiguienteInterno(5, 9, 3)
        );
    }

    public function test_no_toca_lineas_ajenas_si_el_erp_no_tiene_ese_concepto(): void
    {
        $erp = [
            ['concepto' => 50, 'importe' => 10.0],
        ];
        $concmov = [
            ['concepto' => 50, 'importe' => 10.0],
            ['concepto' => 1, 'importe' => 321079582.33],
        ];

        $part = ComprobanteProveedorConcmovPertenenciaSupport::particionar($erp, $concmov);

        $this->assertTrue($part['ok']);
        $this->assertCount(1, $part['de_erp']);
        $this->assertCount(1, $part['de_otras']);
        $this->assertSame(1, $part['de_otras'][0]['concepto']);
    }
}

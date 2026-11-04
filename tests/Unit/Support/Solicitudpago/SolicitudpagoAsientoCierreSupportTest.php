<?php

namespace Tests\Unit\Support\Solicitudpago;

use App\Support\Solicitudpago\SolicitudpagoAsientoCierreSupport;
use PHPUnit\Framework\TestCase;

class SolicitudpagoAsientoCierreSupportTest extends TestCase
{
    public function test_una_sola_pierna_no_deja_cerrar(): void
    {
        $eval = SolicitudpagoAsientoCierreSupport::evaluar([
            'empresa_ids' => [2],
            'cuentacontable_ids' => [1371],
            'centrocosto_ids' => [12],
            'debe_haberes' => ['D'],
            'montos_debe' => [10116927.65],
            'montos_haber' => [0],
        ]);

        $this->assertNotNull($eval['error']);
        $this->assertStringContainsString('no balancea', $eval['error']);
        $this->assertSame([], $eval['lineas']);
    }

    public function test_debe_y_haber_con_el_mismo_importe_balancean(): void
    {
        $eval = SolicitudpagoAsientoCierreSupport::evaluar([
            'empresa_ids' => [2, 2],
            'cuentacontable_ids' => [988, 1371],
            'centrocosto_ids' => [12, 12],
            'debe_haberes' => ['H', 'D'],
            'montos_debe' => ['', '10.116.927,65'],
            'montos_haber' => ['10.116.927,65', ''],
        ]);

        $this->assertNull($eval['error']);
        $this->assertCount(2, $eval['lineas']);
        $this->assertSame('H', $eval['lineas'][0]['debe_haber']);
        $this->assertSame('D', $eval['lineas'][1]['debe_haber']);
        $this->assertEqualsWithDelta(10116927.65, $eval['total_debe'], 0.001);
        $this->assertEqualsWithDelta(10116927.65, $eval['total_haber'], 0.001);
    }

    public function test_el_mismo_importe_en_debe_y_haber_de_un_renglon_no_simula_balance(): void
    {
        $eval = SolicitudpagoAsientoCierreSupport::evaluar([
            'empresa_ids' => [2],
            'cuentacontable_ids' => [1371],
            'debe_haberes' => ['D'],
            'montos_debe' => [1000],
            'montos_haber' => [1000],
        ]);

        $this->assertNotNull($eval['error']);
        $this->assertStringContainsString('no balancea', $eval['error']);
    }

    public function test_linea_con_importe_sin_empresa_no_se_descarta(): void
    {
        $eval = SolicitudpagoAsientoCierreSupport::evaluar([
            'empresa_ids' => [2, 0],
            'cuentacontable_ids' => [1371, 988],
            'debe_haberes' => ['D', 'H'],
            'montos_cuenta' => [500, 500],
        ]);

        $this->assertNotNull($eval['error']);
        $this->assertStringContainsString('sin empresa', $eval['error']);
    }
}

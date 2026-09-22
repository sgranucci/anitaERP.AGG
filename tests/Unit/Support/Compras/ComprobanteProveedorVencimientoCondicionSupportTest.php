<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorVencimientoCondicionSupport;
use PHPUnit\Framework\TestCase;
use stdClass;

class ComprobanteProveedorVencimientoCondicionSupportTest extends TestCase
{
    public function test_fechas_desde_plantilla_suman_plazo_a_fecha_base(): void
    {
        $c7 = new stdClass;
        $c7->plazo = 7;
        $c30 = new stdClass;
        $c30->plazo = 30;

        $this->assertSame(
            ['2026-09-14'],
            ComprobanteProveedorVencimientoCondicionSupport::fechasDesdePlantilla([$c7], '2026-09-07')
        );
        $this->assertSame(
            ['2026-09-14', '2026-10-07'],
            ComprobanteProveedorVencimientoCondicionSupport::fechasDesdePlantilla([$c7, $c30], '2026-09-07')
        );
    }

    public function test_aplicar_a_cuotas_solo_si_misma_cantidad(): void
    {
        $c7 = new stdClass;
        $c7->plazo = 7;
        $c7->tipoplazo = 'D';
        $c7->fechavencimiento = null;

        // Sin plantilla real en DB: aplicarACuotas con id null no cambia.
        $cuotas = [[
            'numero_cuota' => 1,
            'fechavencimiento' => '2027-01-30',
            'monto' => 100.0,
        ]];
        $this->assertSame(
            '2027-01-30',
            ComprobanteProveedorVencimientoCondicionSupport::aplicarACuotas($cuotas, null, '2026-09-07')[0]['fechavencimiento']
        );
    }
}

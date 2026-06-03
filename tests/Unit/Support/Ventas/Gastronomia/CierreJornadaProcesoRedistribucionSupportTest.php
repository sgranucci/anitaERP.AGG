<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\CierreJornadaProcesoClasificacionSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoMedioSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoRedistribucionSupport;
use Tests\TestCase;

class CierreJornadaProcesoRedistribucionSupportTest extends TestCase
{
    public function test_facturado_totem_qr_pasa_a_efectivo_en_redistribucion(): void
    {
        $movimientos = [
            [
                'waitry_order_id' => 2,
                'total' => 100.0,
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_FACTURADO_TOTEM,
                'medio_waitry_clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR,
                'facturada_erp' => true,
            ],
        ];

        $resultado = CierreJornadaProcesoRedistribucionSupport::aplicar($movimientos, 1000.0, 10.0);

        $this->assertSame(100.0, $resultado['objetivo_importe']);
        $this->assertSame(100.0, $resultado['asignado_sin_facturar_a_efectivo']);
        $this->assertSame(
            CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO,
            $resultado['movimientos'][0]['medio_pago_planificado'],
        );
    }
}

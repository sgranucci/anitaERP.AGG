<?php

namespace Tests\Unit\Services\Caja;

use App\Services\Caja\IngresoEgresoComprobanteIvaAsientoVinculoService;
use PHPUnit\Framework\TestCase;

class IngresoEgresoComprobanteIvaAsientoVinculoServiceTest extends TestCase
{
    public function test_empata_por_codigo_cuando_el_id_de_cuenta_es_de_otra_empresa(): void
    {
        $pendientes = [
            ['cuenta_id' => 1075, 'codigo' => '114010009', 'monto' => 83758.85],
            ['cuenta_id' => 1073, 'codigo' => '114010007', 'monto' => 100.0],
        ];

        $this->assertSame(0, IngresoEgresoComprobanteIvaAsientoVinculoService::indicePendiente(
            $pendientes,
            130,
            '114010009',
            83758.85
        ));
    }

    public function test_el_id_de_la_empresa_gana_sobre_el_codigo(): void
    {
        $pendientes = [
            ['cuenta_id' => 130, 'codigo' => '114010009', 'monto' => 10.0],
            ['cuenta_id' => 1075, 'codigo' => '114010009', 'monto' => 10.0],
        ];

        $this->assertSame(1, IngresoEgresoComprobanteIvaAsientoVinculoService::indicePendiente(
            $pendientes,
            1075,
            '114010009',
            10.0
        ));
    }
}

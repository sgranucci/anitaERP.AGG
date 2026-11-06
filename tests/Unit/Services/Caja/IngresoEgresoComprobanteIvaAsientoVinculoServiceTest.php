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

    public function test_tolera_centavo_de_cuadre_del_asiento(): void
    {
        $pendientes = [
            [
                'cuenta_id' => 385,
                'codigo' => '513000012',
                'monto' => 31233.96,
                'comprobante_proveedor_concepto_id' => 73932,
            ],
            [
                'cuenta_id' => 96,
                'codigo' => '114210001',
                'monto' => 6559.13,
                'comprobante_proveedor_concepto_id' => 73933,
            ],
        ];

        $this->assertSame(0, IngresoEgresoComprobanteIvaAsientoVinculoService::indicePendiente(
            $pendientes,
            385,
            '513000012',
            31233.97
        ));
    }

    public function test_no_empata_si_la_diferencia_supera_la_tolerancia(): void
    {
        $pendientes = [
            ['cuenta_id' => 385, 'codigo' => '513000012', 'monto' => 100.0],
        ];

        $this->assertNull(IngresoEgresoComprobanteIvaAsientoVinculoService::indicePendiente(
            $pendientes,
            385,
            '513000012',
            100.10
        ));
    }

    public function test_indice_por_concepto_id_respeta_la_fk(): void
    {
        $pendientes = [
            ['comprobante_proveedor_concepto_id' => 10],
            ['comprobante_proveedor_concepto_id' => 20],
        ];

        $this->assertSame(1, IngresoEgresoComprobanteIvaAsientoVinculoService::indicePendientePorConceptoId($pendientes, 20));
        $this->assertNull(IngresoEgresoComprobanteIvaAsientoVinculoService::indicePendientePorConceptoId($pendientes, 99));
    }
}

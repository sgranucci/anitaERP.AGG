<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\IngresoEgresoBancoAsientoSupport;
use Tests\TestCase;

class IngresoEgresoBancoAsientoSupportTest extends TestCase
{
    public function test_pasar_la_cuenta_del_mayor_a_otro_banco_reasigna_la_caja(): void
    {
        $cambios = IngresoEgresoBancoAsientoSupport::resolverReasignaciones(
            [[
                'linea_id' => 10,
                'cuentacaja_id' => 226,
                'codigo' => '226',
                'codigo_contable' => '111020-226',
                'monto' => 31113334.11,
            ]],
            [
                ['codigo_contable' => '111050-031', 'monto' => 31113334.11],
                ['codigo_contable' => '410010-001', 'monto' => -31113334.11],
            ],
            [
                ['id' => 226, 'codigo' => '226', 'codigo_contable' => '111020-226'],
                ['id' => 2119, 'codigo' => '2119', 'codigo_contable' => '111050-031'],
            ]
        );

        $this->assertSame([[
            'linea_id' => 10,
            'cuentacaja_id_anterior' => 226,
            'cuentacaja_id_nuevo' => 2119,
            'codigo_anterior' => '226',
            'codigo_nuevo' => '2119',
        ]], $cambios);
    }

    public function test_si_el_banco_sigue_en_el_asiento_no_toca_la_caja(): void
    {
        $cambios = IngresoEgresoBancoAsientoSupport::resolverReasignaciones(
            [[
                'linea_id' => 10,
                'cuentacaja_id' => 226,
                'codigo' => '226',
                'codigo_contable' => '111020-226',
                'monto' => 100,
            ]],
            [
                ['codigo_contable' => '111020-226', 'monto' => 100],
                ['codigo_contable' => '410010-001', 'monto' => -50],
                ['codigo_contable' => '410010-002', 'monto' => -50],
            ],
            [
                ['id' => 226, 'codigo' => '226', 'codigo_contable' => '111020-226'],
                ['id' => 2119, 'codigo' => '2119', 'codigo_contable' => '111050-031'],
            ]
        );

        $this->assertSame([], $cambios);
    }

    public function test_dos_cajas_con_la_misma_cuenta_contable_no_elige(): void
    {
        $cambios = IngresoEgresoBancoAsientoSupport::resolverReasignaciones(
            [[
                'linea_id' => 10,
                'cuentacaja_id' => 226,
                'codigo' => '226',
                'codigo_contable' => '111020-226',
                'monto' => 100,
            ]],
            [
                ['codigo_contable' => '111050-031', 'monto' => 100],
                ['codigo_contable' => '410010-001', 'monto' => -100],
            ],
            [
                ['id' => 2119, 'codigo' => '2119', 'codigo_contable' => '111050-031'],
                ['id' => 2199, 'codigo' => '2199', 'codigo_contable' => '111050.031'],
            ]
        );

        $this->assertSame([], $cambios);
    }

    public function test_transferencia_solo_mueve_la_pierna_que_salio_del_asiento(): void
    {
        $cambios = IngresoEgresoBancoAsientoSupport::resolverReasignaciones(
            [
                [
                    'linea_id' => 1,
                    'cuentacaja_id' => 226,
                    'codigo' => '226',
                    'codigo_contable' => '111020-226',
                    'monto' => 500,
                ],
                [
                    'linea_id' => 2,
                    'cuentacaja_id' => 300,
                    'codigo' => '300',
                    'codigo_contable' => '111030-300',
                    'monto' => -500,
                ],
            ],
            [
                ['codigo_contable' => '111050-031', 'monto' => 500],
                ['codigo_contable' => '111030-300', 'monto' => -500],
            ],
            [
                ['id' => 226, 'codigo' => '226', 'codigo_contable' => '111020-226'],
                ['id' => 2119, 'codigo' => '2119', 'codigo_contable' => '111050-031'],
                ['id' => 300, 'codigo' => '300', 'codigo_contable' => '111030-300'],
            ]
        );

        $this->assertCount(1, $cambios);
        $this->assertSame(1, $cambios[0]['linea_id']);
        $this->assertSame(2119, $cambios[0]['cuentacaja_id_nuevo']);
    }
}

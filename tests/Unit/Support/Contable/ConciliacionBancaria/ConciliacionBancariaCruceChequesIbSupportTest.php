<?php

namespace Tests\Unit\Support\Contable\ConciliacionBancaria;

use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaCruceChequesIbSupport;
use Tests\TestCase;

class ConciliacionBancariaCruceChequesIbSupportTest extends TestCase
{
    public function test_cruza_ok_por_numero_e_importe_y_codigo_9(): void
    {
        $movs = [
            [
                'process_date' => '2026-11-12',
                'voucher_number' => '82048410',
                'code_description_ib' => 'CH DEP 48',
                'grouping_code_ib' => '2 1 1',
                'operation_code_ib' => '001',
                'amount' => 371416.37,
                'debit_credit_type' => 'D',
            ],
            [
                'process_date' => '2026-11-12',
                'voucher_number' => '82048272',
                'code_description_ib' => 'CH/PAG',
                'grouping_code_ib' => '2 1 1',
                'operation_code_ib' => '917',
                'amount' => 650666.16,
                'debit_credit_type' => 'D',
            ],
        ];

        $indice = [
            '82048410' => [[
                'id' => 1,
                'numerocheque' => '82048410',
                'monto' => 371416.37,
                'estado' => '*',
                'estado_nombre' => 'DEBITADO',
                'origen' => 'E',
                'fechapago' => '2026-11-05',
                'fechaemision' => '2026-11-04',
                'chequera' => '504',
                'tipocheque' => 'D',
                'op' => '125852',
                'op_estado' => 'CONFIRMADA',
            ]],
            '82048272' => [[
                'id' => 2,
                'numerocheque' => '82048272',
                'monto' => 650666.16,
                'estado' => '*',
                'estado_nombre' => 'DEBITADO',
                'origen' => 'E',
                'fechapago' => '2026-11-06',
                'fechaemision' => '2026-10-21',
                'chequera' => '504',
                'tipocheque' => 'D',
                'op' => '125594',
                'op_estado' => 'CONFIRMADA',
            ]],
        ];

        $out = ConciliacionBancariaCruceChequesIbSupport::cruzar($movs, $indice);

        $this->assertSame(2, $out['resumen']['total']);
        $this->assertSame(2, $out['resumen']['ok']);
        $this->assertSame(0, $out['resumen']['faltan']);
        $this->assertSame(2, $out['resumen']['codigo_9']);
        $this->assertSame(ConciliacionBancariaCruceChequesIbSupport::MATCH_OK, $out['filas'][0]['match']);
        $this->assertSame('125852', $out['filas'][0]['erp_op']);
        $this->assertSame(9, $out['filas'][0]['codigo']);
        $this->assertSame(9, $out['filas'][1]['codigo']);
    }

    public function test_marca_falta_e_importe_distinto(): void
    {
        $movs = [
            [
                'process_date' => '2026-11-12',
                'voucher_number' => '82049999',
                'code_description_ib' => 'CH DEP 48',
                'grouping_code_ib' => '2 1 1',
                'amount' => 1000,
                'debit_credit_type' => 'D',
            ],
            [
                'process_date' => '2026-11-12',
                'voucher_number' => '82048888',
                'code_description_ib' => 'CH DEP 48',
                'grouping_code_ib' => '2 1 1',
                'amount' => 2000,
                'debit_credit_type' => 'D',
            ],
        ];

        $indice = [
            '82048888' => [[
                'id' => 9,
                'numerocheque' => '82048888',
                'monto' => 1999.50,
                'estado' => ' ',
                'estado_nombre' => 'DIFERIDO',
                'origen' => 'E',
                'fechapago' => '2026-11-10',
                'fechaemision' => '2026-11-01',
                'chequera' => '504',
                'tipocheque' => 'D',
                'op' => '1',
                'op_estado' => 'CONFIRMADA',
            ]],
        ];

        $out = ConciliacionBancariaCruceChequesIbSupport::cruzar($movs, $indice);

        $this->assertSame(1, $out['resumen']['faltan']);
        $this->assertSame(1, $out['resumen']['importe_distinto']);
        $this->assertSame(ConciliacionBancariaCruceChequesIbSupport::MATCH_FALTA, $out['filas'][0]['match']);
        $this->assertSame(ConciliacionBancariaCruceChequesIbSupport::MATCH_IMPORTE, $out['filas'][1]['match']);
    }

    public function test_enriquecer_no_pisa_si_ya_existe(): void
    {
        $resultado = [
            'cruce_cheques_ib' => [
                'filas' => [['match' => 'OK_NRO_Y_IMPORTE']],
                'resumen' => ['total' => 1],
            ],
        ];

        $out = ConciliacionBancariaCruceChequesIbSupport::enriquecerResultado($resultado);

        $this->assertSame(1, $out['cruce_cheques_ib']['resumen']['total']);
        $this->assertCount(1, $out['cruce_cheques_ib']['filas']);
    }

    public function test_enriquecer_vacio_sin_movimientos(): void
    {
        $out = ConciliacionBancariaCruceChequesIbSupport::enriquecerResultado([
            'empresa_id' => 1,
            'cuentacaja' => ['id' => 10],
            'movimientos_banco' => [],
        ]);

        $this->assertSame(0, $out['cruce_cheques_ib']['resumen']['total']);
        $this->assertSame([], $out['cruce_cheques_ib']['filas']);
    }
}

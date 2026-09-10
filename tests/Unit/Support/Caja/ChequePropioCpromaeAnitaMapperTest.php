<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\ChequePropioCpromaeAnitaMapper;
use PHPUnit\Framework\TestCase;

class ChequePropioCpromaeAnitaMapperTest extends TestCase
{
    public function test_cheque_diferido_pesos_igual_anita_nativo(): void
    {
        $out = ChequePropioCpromaeAnitaMapper::mapear([
            'cuenta' => '127',
            'nro' => 79030366,
            'fecha_emision' => '2026-09-10',
            'fecha_pago' => '2026-09-18',
            'importe' => 1960396.9,
            'proveedor' => '2283',
            'entregado' => 'YAFEMA S.R.L.',
            'anombrede' => 'YAFEMA S.R.L.',
            'nro_op' => 124828,
            'moneda_id' => 1,
            'cotizacion' => 0,
            'empresa' => 1,
            'chequera_codigo' => '1',
            'chequera_tipo' => 'F',
            'caracter' => 'N',
            'estado_erp' => '*',
        ]);

        $this->assertSame('00000127', $out['cpro_cuenta']);
        $this->assertSame('79030366', $out['cpro_nro_cheque']);
        $this->assertSame('20260918', $out['cpro_fecha_cheque']);
        $this->assertSame('20260910', $out['cpro_fecha_emision']);
        $this->assertSame('002283', $out['cpro_proveedor']);
        $this->assertSame('124828', $out['cpro_nro_op']);
        $this->assertSame('1', $out['cpro_cotizacion']);
        $this->assertSame(' ', $out['cpro_estado']);
        $this->assertSame('E', $out['cpro_para_dep']);
        $this->assertSame('N', $out['cpro_negociable']);
        $this->assertSame(' ', $out['cpro_estado_banco']);
        $this->assertSame('0', $out['cpro_fecha_entrega']);
        $this->assertSame('0', $out['cpro_sucursal_pago']);
        $this->assertSame('0', $out['cpro_tipo_distrib']);
        $this->assertSame(' ', $out['cpro_nro_e_cheq']);
        $this->assertSame('1', $out['cpro_modelo']);
    }

    public function test_para_dep_y_negociable_explicitos(): void
    {
        $out = ChequePropioCpromaeAnitaMapper::mapear([
            'cuenta' => '127',
            'nro' => 10,
            'fecha_emision' => '2026-09-10',
            'fecha_pago' => '2026-09-10',
            'importe' => 1,
            'chequera_tipo' => 'F',
            'para_dep' => 'N',
            'negociable' => 'E',
            'nro_echeq' => 'ECH-99',
            'fecha_entrega' => '2026-09-11',
        ]);
        $this->assertSame('N', $out['cpro_para_dep']);
        $this->assertSame('E', $out['cpro_negociable']);
        $this->assertSame('ECH-99', $out['cpro_nro_e_cheq']);
        $this->assertSame('20260911', $out['cpro_fecha_entrega']);
    }

    public function test_echeq_pone_negociable_y_nro(): void
    {
        $out = ChequePropioCpromaeAnitaMapper::mapear([
            'cuenta' => '127',
            'nro' => 99,
            'fecha_emision' => '2026-09-10',
            'fecha_pago' => '2026-09-10',
            'importe' => 10,
            'chequera_tipo' => 'E',
            'chequera_codigo' => '9',
        ]);
        $this->assertSame('E', $out['cpro_negociable']);
        $this->assertSame('99', $out['cpro_nro_e_cheq']);
        $this->assertSame('*', $out['cpro_estado']);
    }
}

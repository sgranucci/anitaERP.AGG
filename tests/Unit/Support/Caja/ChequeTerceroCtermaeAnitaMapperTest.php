<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\ChequeTerceroCtermaeAnitaMapper;
use PHPUnit\Framework\TestCase;

class ChequeTerceroCtermaeAnitaMapperTest extends TestCase
{
    public function test_negociable_desde_interior(): void
    {
        $this->assertSame('E', ChequeTerceroCtermaeAnitaMapper::negociableDesdeInterior('3'));
        $this->assertSame('E', ChequeTerceroCtermaeAnitaMapper::negociableDesdeInterior(' 3 '));
        $this->assertSame('N', ChequeTerceroCtermaeAnitaMapper::negociableDesdeInterior('0'));
        $this->assertSame('N', ChequeTerceroCtermaeAnitaMapper::negociableDesdeInterior('1'));
        $this->assertSame('N', ChequeTerceroCtermaeAnitaMapper::negociableDesdeInterior('2'));
        $this->assertSame('N', ChequeTerceroCtermaeAnitaMapper::negociableDesdeInterior(null));
        $this->assertSame('N', ChequeTerceroCtermaeAnitaMapper::negociableDesdeInterior(''));
    }

    public function test_interior_desde_negociable(): void
    {
        $this->assertSame('3', ChequeTerceroCtermaeAnitaMapper::interiorDesdeNegociable('E', '2'));
        $this->assertSame('2', ChequeTerceroCtermaeAnitaMapper::interiorDesdeNegociable('N', '2'));
        $this->assertSame('1', ChequeTerceroCtermaeAnitaMapper::interiorDesdeNegociable('N', ''));
    }

    public function test_a_atributos_erp_mapea_echeq(): void
    {
        $row = (object) [
            'cter_nro_interno' => '97591',
            'cter_nro_cheque' => '0',
            'cter_estado' => ' ',
            'cter_fecha_cheque' => '20260916',
            'cter_fecha_ingreso' => '20260916',
            'cter_importe' => '1000000',
            'cter_cod_mon' => '1',
            'cter_cotizacion' => '1',
            'cter_interior' => '3',
            'cter_nro_e_cheq' => '67928118',
        ];

        $out = ChequeTerceroCtermaeAnitaMapper::aAtributosErp($row, [
            'empresa_id' => 1,
            'cliente_id' => null,
            'proveedor_id' => null,
            'banco_id' => null,
        ]);

        $this->assertSame('E', $out['negociable']);
        $this->assertSame('67928118', $out['nro_echeq']);
        $this->assertSame('97591', $out['numerocheque']);
    }

    public function test_a_atributos_erp_mapea_fisico(): void
    {
        $row = (object) [
            'cter_nro_interno' => '80790',
            'cter_nro_cheque' => '7662794',
            'cter_estado' => ' ',
            'cter_fecha_cheque' => '20210101',
            'cter_fecha_ingreso' => '20201109',
            'cter_importe' => '100',
            'cter_cod_mon' => '1',
            'cter_cotizacion' => '1',
            'cter_interior' => '0',
            'cter_nro_e_cheq' => ' ',
        ];

        $out = ChequeTerceroCtermaeAnitaMapper::aAtributosErp($row, [
            'empresa_id' => 1,
            'cliente_id' => null,
            'proveedor_id' => null,
            'banco_id' => null,
        ]);

        $this->assertSame('N', $out['negociable']);
        $this->assertNull($out['nro_echeq']);
        $this->assertSame('7662794', $out['numerocheque']);
    }
}

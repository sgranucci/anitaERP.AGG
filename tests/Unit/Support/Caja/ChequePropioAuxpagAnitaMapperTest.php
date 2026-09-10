<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\ChequePropioAuxpagAnitaMapper;
use PHPUnit\Framework\TestCase;

class ChequePropioAuxpagAnitaMapperTest extends TestCase
{
    public function test_sucursal_es_nro_cheque_y_cob_es_empresa(): void
    {
        $out = ChequePropioAuxpagAnitaMapper::sucursales(79030366, 1);
        $this->assertSame('79030366', $out['axp_sucursal']);
        $this->assertSame('1', $out['axp_sucursal_cob']);
    }

    public function test_detecta_campos_invertidos(): void
    {
        $problemas = ChequePropioAuxpagAnitaMapper::discrepancias(79030366, 1, [
            'axp_sucursal' => '1',
            'axp_sucursal_cob' => '79030366',
        ]);
        $this->assertCount(2, $problemas);
        $this->assertTrue(str_contains(implode(' ', $problemas), 'axp_sucursal'));
        $this->assertTrue(str_contains(implode(' ', $problemas), 'axp_sucursal_cob'));
    }

    public function test_nativo_sin_discrepancia(): void
    {
        $this->assertSame([], ChequePropioAuxpagAnitaMapper::discrepancias(79030365, 1, [
            'axp_sucursal' => '79030365',
            'axp_sucursal_cob' => '1',
        ]));
    }
}

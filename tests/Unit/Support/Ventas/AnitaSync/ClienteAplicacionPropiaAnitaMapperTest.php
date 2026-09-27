<?php

namespace Tests\Unit\Support\Ventas\AnitaSync;

use App\Support\Ventas\AnitaSync\ClienteAplicacionPropiaAnitaMapper;
use PHPUnit\Framework\TestCase;

class ClienteAplicacionPropiaAnitaMapperTest extends TestCase
{
    public function test_anticipo_se_aplica_contra_si_mismo_en_cuota_cero_con_apa(): void
    {
        $coa = [
            'tipo' => 'COA',
            'letra' => 'X',
            'sucursal' => 1,
            'numero' => 29277,
            'cod_mon' => '1',
            'cotizacion' => 1,
            'empresa' => 1,
        ];
        $valores = ClienteAplicacionPropiaAnitaMapper::valoresAplmov($coa, 0, 'APA', 0, 12, '20260927', 1500);
        $this->assertStringContainsString("'COA'", $valores);
        $this->assertStringContainsString("'0'", $valores);
        $this->assertStringContainsString("'APA'", $valores);
        $this->assertStringContainsString("'12'", $valores);
        $this->assertStringContainsString("'1500.0000'", $valores);

        $apa = ClienteAplicacionPropiaAnitaMapper::valoresClimovApa($coa, '000067', 12, 1500, '20260927', false);
        $this->assertStringContainsString("'000067'", $apa);
        $this->assertStringContainsString("'APA'", $apa);
        $this->assertStringContainsString("'29277'", $apa);
        $this->assertStringContainsString('cliv_empresa', ClienteAplicacionPropiaAnitaMapper::camposClimovApa(true));
        $this->assertStringNotContainsString('cliv_empresa', ClienteAplicacionPropiaAnitaMapper::camposClimovApa(false));
    }

    public function test_nota_de_credito_referencia_anc(): void
    {
        $nota = [
            'tipo' => 'NCD',
            'letra' => 'A',
            'sucursal' => 19,
            'numero' => 10,
            'cod_mon' => '1',
            'cotizacion' => 1,
        ];
        $valores = ClienteAplicacionPropiaAnitaMapper::valoresAplmov($nota, 1, 'ANC', 19, 10, '20260422', 462499.99);
        $this->assertStringContainsString("'NCD'", $valores);
        $this->assertStringContainsString("'ANC'", $valores);
        $this->assertStringContainsString("'462499.9900'", $valores);
        $where = ClienteAplicacionPropiaAnitaMapper::whereAplmov($nota, 1, 'ANC', '20260422', 462499.99);
        $this->assertStringContainsString("aplv_nro_cuota = '1'", $where);
        $this->assertStringContainsString("aplv_ref_tipo = 'ANC'", $where);
    }
}

<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\DepmaeAnitaExclusionSupport;
use Tests\TestCase;

class DepmaeAnitaExclusionSupportTest extends TestCase
{
    public function test_omite_codigo_numerico_mayor_a_umbral(): void
    {
        config(['stock.depmae_anita_codigo_maximo' => 100000]);

        $this->assertTrue(DepmaeAnitaExclusionSupport::debeOmitirCodigo('213205'));
        $this->assertTrue(DepmaeAnitaExclusionSupport::debeOmitirCodigo('433234'));
    }

    public function test_permite_codigo_numerico_operativo(): void
    {
        config(['stock.depmae_anita_codigo_maximo' => 100000]);

        $this->assertFalse(DepmaeAnitaExclusionSupport::debeOmitirCodigo('1'));
        $this->assertFalse(DepmaeAnitaExclusionSupport::debeOmitirCodigo('100000'));
        $this->assertFalse(DepmaeAnitaExclusionSupport::debeOmitirCodigo('14'));
    }

    public function test_permite_codigo_alfanumerico(): void
    {
        config(['stock.depmae_anita_codigo_maximo' => 100000]);

        $this->assertFalse(DepmaeAnitaExclusionSupport::debeOmitirCodigo('KSA 14'));
        $this->assertFalse(DepmaeAnitaExclusionSupport::debeOmitirCodigo('R1'));
    }
}

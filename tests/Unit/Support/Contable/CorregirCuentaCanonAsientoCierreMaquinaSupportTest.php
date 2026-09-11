<?php

namespace Tests\Unit\Support\Contable;

use App\Support\Contable\CorregirCuentaCanonAsientoCierreMaquinaSupport;
use Tests\TestCase;

class CorregirCuentaCanonAsientoCierreMaquinaSupportTest extends TestCase
{
    public function test_mapa_mueve_canones_de_sala_bingo_a_maquinas(): void
    {
        $this->assertSame('521010001', CorregirCuentaCanonAsientoCierreMaquinaSupport::MAPA_CODIGO['521020001']);
        $this->assertSame('521010002', CorregirCuentaCanonAsientoCierreMaquinaSupport::MAPA_CODIGO['521020002']);
        $this->assertArrayNotHasKey('521020003', CorregirCuentaCanonAsientoCierreMaquinaSupport::MAPA_CODIGO);
    }
}

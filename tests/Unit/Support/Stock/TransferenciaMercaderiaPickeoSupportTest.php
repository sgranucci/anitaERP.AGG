<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\TransferenciaMercaderiaPickeoSupport;
use PHPUnit\Framework\TestCase;

class TransferenciaMercaderiaPickeoSupportTest extends TestCase
{
    public function test_variantes_incluye_codigo_y_sin_ceros_a_izquierda(): void
    {
        $this->assertSame(['7791004000082'], TransferenciaMercaderiaPickeoSupport::variantesCodigo('7791004000082'));
        $this->assertSame(
            ['07791004000082', '7791004000082'],
            TransferenciaMercaderiaPickeoSupport::variantesCodigo('07791004000082')
        );
    }

    public function test_variantes_vacio_si_no_hay_codigo(): void
    {
        $this->assertSame([], TransferenciaMercaderiaPickeoSupport::variantesCodigo(''));
        $this->assertSame([], TransferenciaMercaderiaPickeoSupport::variantesCodigo('   '));
    }

    public function test_variantes_agrega_normalizado_en_mayusculas(): void
    {
        $this->assertSame(['v0432', 'V0432'], TransferenciaMercaderiaPickeoSupport::variantesCodigo(' v0432 '));
    }
}

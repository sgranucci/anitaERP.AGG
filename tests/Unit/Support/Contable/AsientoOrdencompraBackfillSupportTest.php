<?php

namespace Tests\Unit\Support\Contable;

use App\Support\Contable\AsientoOrdencompraBackfillSupport;
use PHPUnit\Framework\TestCase;

class AsientoOrdencompraBackfillSupportTest extends TestCase
{
    public function test_usa_recepcion_cuando_coincide_o_solo_hay_rec(): void
    {
        $this->assertSame(
            ['ordencompra_id' => 10, 'origen' => 'recepcion'],
            AsientoOrdencompraBackfillSupport::resolverOc(10, null)
        );
        $this->assertSame(
            ['ordencompra_id' => 10, 'origen' => 'recepcion'],
            AsientoOrdencompraBackfillSupport::resolverOc(10, 10)
        );
    }

    public function test_usa_comprobante_si_no_hay_recepcion(): void
    {
        $this->assertSame(
            ['ordencompra_id' => 55, 'origen' => 'comprobante'],
            AsientoOrdencompraBackfillSupport::resolverOc(null, 55)
        );
        $this->assertSame(
            ['ordencompra_id' => 55, 'origen' => 'comprobante'],
            AsientoOrdencompraBackfillSupport::resolverOc(0, 55)
        );
    }

    public function test_conflicto_o_sin_fuente_devuelve_null(): void
    {
        $this->assertNull(AsientoOrdencompraBackfillSupport::resolverOc(1, 2));
        $this->assertNull(AsientoOrdencompraBackfillSupport::resolverOc(null, null));
        $this->assertNull(AsientoOrdencompraBackfillSupport::resolverOc(0, 0));
    }
}

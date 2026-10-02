<?php

namespace Tests\Unit\Support\Contable;

use App\Support\Contable\AsientoOrigenProcesoSupport;
use PHPUnit\Framework\TestCase;

class AsientoOrigenProcesoSupportTest extends TestCase
{
    public function test_orden_de_compra_sola_no_bloquea_un_asiento_contable(): void
    {
        $asiento = (object) [
            'ordencompra_id' => 25202,
            'recepcionproveedor_id' => null,
            'comprobante_proveedor_id' => null,
        ];

        $this->assertFalse(AsientoOrigenProcesoSupport::tieneOrigenProceso($asiento));
        $this->assertSame('', AsientoOrigenProcesoSupport::mensajeBloqueo($asiento, 'revertir'));
        $this->assertSame(['ordencompra_id' => 25202], AsientoOrigenProcesoSupport::fksActivas($asiento));
    }

    public function test_recepcion_con_orden_de_compra_sigue_bloqueando(): void
    {
        $asiento = [
            'ordencompra_id' => 25202,
            'recepcionproveedor_id' => 68532,
        ];

        $this->assertTrue(AsientoOrigenProcesoSupport::tieneOrigenProceso($asiento));
        $this->assertSame(
            ['recepcionproveedor_id' => 68532],
            AsientoOrigenProcesoSupport::fksOrigenProceso($asiento)
        );
        $this->assertStringContainsString(
            'Recepción proveedor #68532',
            AsientoOrigenProcesoSupport::mensajeBloqueo($asiento, 'revertir')
        );
        $this->assertStringNotContainsString(
            'Orden de compra',
            AsientoOrigenProcesoSupport::mensajeBloqueo($asiento, 'revertir')
        );
    }
}

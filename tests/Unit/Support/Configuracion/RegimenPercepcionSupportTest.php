<?php

namespace Tests\Unit\Support\Configuracion;

use App\Support\Configuracion\RegimenPercepcionSupport;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD). Mínimo de gravado como Anita: tot_grav - minimo >= 0.01.
 */
class RegimenPercepcionSupportTest extends TestCase
{
    public function test_gravado_bajo_el_minimo_no_supera(): void
    {
        self::assertFalse(RegimenPercepcionSupport::superaMinimoBase(91391.46, 100000));
    }

    public function test_gravado_en_el_piso_supera(): void
    {
        self::assertTrue(RegimenPercepcionSupport::superaMinimoBase(100000.01, 100000));
    }

    public function test_minimo_cero_cualquier_gravado_positivo(): void
    {
        self::assertTrue(RegimenPercepcionSupport::superaMinimoBase(0.01, 0));
        self::assertFalse(RegimenPercepcionSupport::superaMinimoBase(0, 0));
    }

    public function test_minimo_importe_no_percibe_si_no_supera(): void
    {
        self::assertFalse(RegimenPercepcionSupport::superaMinimoImporte(10.00, 10.00));
        self::assertTrue(RegimenPercepcionSupport::superaMinimoImporte(10.01, 10.00));
    }
}

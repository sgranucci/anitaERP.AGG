<?php

namespace Tests\Unit\Support\Contable;

use App\Support\Contable\AsientoCargaManualSupport;
use PHPUnit\Framework\TestCase;

class AsientoCargaManualSupportTest extends TestCase
{
    public function test_detecta_flags_manuales(): void
    {
        $this->assertTrue(AsientoCargaManualSupport::flagEsManual('S'));
        $this->assertTrue(AsientoCargaManualSupport::flagEsManual('s'));
        $this->assertTrue(AsientoCargaManualSupport::flagEsManual('1'));
        $this->assertTrue(AsientoCargaManualSupport::flagEsManual('Y'));
        $this->assertFalse(AsientoCargaManualSupport::flagEsManual('N'));
        $this->assertFalse(AsientoCargaManualSupport::flagEsManual('0'));
        $this->assertFalse(AsientoCargaManualSupport::flagEsManual(''));
        $this->assertFalse(AsientoCargaManualSupport::flagEsManual(null));
    }

    public function test_fue_editado_manual_con_al_menos_una_linea(): void
    {
        $this->assertFalse(AsientoCargaManualSupport::fueEditadoManual(['N', 'N', '0']));
        $this->assertTrue(AsientoCargaManualSupport::fueEditadoManual(['N', 'S', 'N']));
        $this->assertTrue(AsientoCargaManualSupport::fueEditadoManual(['1']));
        $this->assertFalse(AsientoCargaManualSupport::fueEditadoManual([]));
        $this->assertFalse(AsientoCargaManualSupport::fueEditadoManual(null));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Configuracion;

use App\Models\Configuracion\Salida;
use App\Support\Configuracion\SalidaImpresionFallbackSupport;
use PHPUnit\Framework\TestCase;

final class SalidaImpresionFallbackSupportTest extends TestCase
{
    public function test_cuenta_marcadores_sprintf_ignorando_porcentaje_escapado(): void
    {
        $this->assertSame(0, SalidaImpresionFallbackSupport::cantidadMarcadoresSprintf('echo ok'));
        $this->assertSame(1, SalidaImpresionFallbackSupport::cantidadMarcadoresSprintf('lp -darmado %s'));
        $this->assertSame(1, SalidaImpresionFallbackSupport::cantidadMarcadoresSprintf('cp "%s" /tmp/x.pdf && echo 100%%'));
        $this->assertSame(2, SalidaImpresionFallbackSupport::cantidadMarcadoresSprintf('./bin/imp_otr %s %s P1'));
    }

    public function test_comando_pdf_exige_un_solo_marcador(): void
    {
        $ok = new Salida(['comando' => 'lp -darmado %s']);
        $ot = new Salida(['comando' => './bin/imp_otr %s %s P1']);
        $vacio = new Salida(['comando' => '']);

        $this->assertTrue(SalidaImpresionFallbackSupport::comandoPdfCompatible($ok));
        $this->assertTrue(SalidaImpresionFallbackSupport::comandoImpresionValido($ok));
        $this->assertFalse(SalidaImpresionFallbackSupport::comandoPdfCompatible($ot));
        $this->assertFalse(SalidaImpresionFallbackSupport::comandoImpresionValido($ot));
        $this->assertFalse(SalidaImpresionFallbackSupport::comandoPdfCompatible($vacio));
        $this->assertFalse(SalidaImpresionFallbackSupport::comandoPdfCompatible(null));
    }
}

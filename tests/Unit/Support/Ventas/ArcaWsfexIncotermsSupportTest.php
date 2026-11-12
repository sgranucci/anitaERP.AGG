<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\ArcaWsfexIncotermsSupport;
use Tests\TestCase;

final class ArcaWsfexIncotermsSupportTest extends TestCase
{
    public function test_codigos_afip_validos(): void
    {
        $this->assertTrue(ArcaWsfexIncotermsSupport::esValido('FOB'));
        $this->assertTrue(ArcaWsfexIncotermsSupport::esValido(' dpu '));
        $this->assertFalse(ArcaWsfexIncotermsSupport::esValido(''));
        $this->assertFalse(ArcaWsfexIncotermsSupport::esValido('DDU'));
        $this->assertFalse(ArcaWsfexIncotermsSupport::esValido('DAF'));
        $this->assertFalse(ArcaWsfexIncotermsSupport::esValido('DES'));
    }

    public function test_mensaje_obligatorio_tipo_19_productos(): void
    {
        $this->assertNull(ArcaWsfexIncotermsSupport::mensajeSiInvalidoParaAuthorize('FOB', 19, 1));
        $this->assertNull(ArcaWsfexIncotermsSupport::mensajeSiInvalidoParaAuthorize('', 20, 1));
        $this->assertNull(ArcaWsfexIncotermsSupport::mensajeSiInvalidoParaAuthorize('', 19, 2));

        $vacio = ArcaWsfexIncotermsSupport::mensajeSiInvalidoParaAuthorize('', 19, 1);
        $this->assertNotNull($vacio);
        $this->assertStringContainsString('Incoterms', $vacio);

        $invalido = ArcaWsfexIncotermsSupport::mensajeSiInvalidoParaAuthorize('DDU', 19, 1);
        $this->assertNotNull($invalido);
        $this->assertStringContainsString('DDU', $invalido);
        $this->assertStringContainsString('1640', $invalido);
    }
}

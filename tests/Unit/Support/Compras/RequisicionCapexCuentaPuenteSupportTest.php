<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\RequisicionCapexCuentaPuenteSupport;
use PHPUnit\Framework\TestCase;

class RequisicionCapexCuentaPuenteSupportTest extends TestCase
{
    public function test_mensaje_de_envio_al_arbol_indica_como_seguir(): void
    {
        $texto = RequisicionCapexCuentaPuenteSupport::mensajeCapexFaltante(
            ['la línea 1 (MAN380000-288 SERV.FLETES AUTOELEVADORES)'],
            true
        );

        $this->assertStringContainsString('No se puede enviar al árbol', $texto);
        $this->assertStringContainsString('MAN380000-288', $texto);
        $this->assertStringContainsString('123010030', $texto);
        $this->assertStringContainsString('Edite la requisición, cargue el CAPEX de esa línea y vuelva a enviarla.', $texto);
    }

    public function test_mensaje_de_varias_lineas_pide_el_capex_de_esas_lineas(): void
    {
        $texto = RequisicionCapexCuentaPuenteSupport::mensajeCapexFaltante(
            ['la línea 1 (A)', 'la línea 2 (B)'],
            true
        );

        $this->assertStringContainsString('imputan', $texto);
        $this->assertStringContainsString('no tienen CAPEX', $texto);
        $this->assertStringContainsString('esas líneas', $texto);
    }
}

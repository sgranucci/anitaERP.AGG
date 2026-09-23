<?php

namespace Tests\Unit\Support\Contable;

use App\Support\Contable\CuentacontableArbolSupport;
use App\Support\Contable\CuentacontableTipocuentaNormalizacionSupport;
use Tests\TestCase;

class CuentacontableTipocuentaNormalizacionSupportTest extends TestCase
{
    public function test_canonicos_y_api_sync(): void
    {
        $this->assertSame('1', CuentacontableArbolSupport::TIPO_IMPUTABLE);
        $this->assertSame('2', CuentacontableArbolSupport::TIPO_TITULO);
        $this->assertSame('Título / Encabezado', CuentacontableArbolSupport::etiquetaTipo('2'));
        $this->assertTrue(method_exists(CuentacontableTipocuentaNormalizacionSupport::class, 'sincronizarDesdeAnita'));
    }
}

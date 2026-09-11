<?php

namespace Tests\Unit\Support\Compras\AnitaSync\ComprobanteProveedor;

use App\Models\Compras\Comprobante_Proveedor;
use App\Support\Compras\AnitaSync\ComprobanteProveedor\ComprobanteProveedorAnitaContext;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorAnitaContextTest extends TestCase
{
    public function test_escape_aplana_crlf_de_leyenda_antes_de_truncar(): void
    {
        $ctx = new ComprobanteProveedorAnitaContext(new Comprobante_Proveedor, 1);
        $leyenda = "comision por procesamiento\r\nmodulo de pagos\r\nadministracion y conciliacion";

        $resultado = $ctx->escape($leyenda, 30);

        $this->assertSame('comision por procesamiento mod', $resultado);
        $this->assertStringNotContainsString("\r", $resultado);
        $this->assertStringNotContainsString("\n", $resultado);
    }

    public function test_escape_translitera_acentos_y_escapa_comillas(): void
    {
        $ctx = new ComprobanteProveedorAnitaContext(new Comprobante_Proveedor, 1);

        $this->assertSame("O''Higgins", $ctx->escape("O'Higgins", 30));
        $this->assertSame('Jose Perez', $ctx->escape('José Pérez', 30));
    }
}

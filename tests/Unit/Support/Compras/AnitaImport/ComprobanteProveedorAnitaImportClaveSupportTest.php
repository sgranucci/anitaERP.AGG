<?php

namespace Tests\Unit\Support\Compras\AnitaImport;

use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportClaveSupport;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorAnitaImportClaveSupportTest extends TestCase
{
    public function test_proveedor_queda_en_6_digitos(): void
    {
        $this->assertSame('003593', ComprobanteProveedorAnitaImportClaveSupport::proveedorCodigoAnita('3593'));
        $this->assertSame('003593', ComprobanteProveedorAnitaImportClaveSupport::proveedorCodigoAnita('003593'));
    }

    public function test_fecha_anita_a_iso(): void
    {
        $this->assertSame('2026-03-15', ComprobanteProveedorAnitaImportClaveSupport::fechaIsoDesdeAnita(20260315));
        $this->assertSame('', ComprobanteProveedorAnitaImportClaveSupport::fechaIsoDesdeAnita(0));
    }

    public function test_clave_incluye_proveedor_y_documento_no(): void
    {
        $this->assertSame(
            '003593|FAC|A|1|123',
            ComprobanteProveedorAnitaImportClaveSupport::clave('3593', 'fac', 'a', 1, 123)
        );
        $this->assertSame(
            'FAC|A|1|123',
            ComprobanteProveedorAnitaImportClaveSupport::claveDocumento('fac', 'a', 1, 123)
        );
    }

    public function test_clave_desde_compra(): void
    {
        $this->assertSame('003593|NCA|A|3|99', ComprobanteProveedorAnitaImportClaveSupport::claveDesdeCompra([
            'com_proveedor' => '3593',
            'com_tipo' => 'NCA',
            'com_letra' => 'A',
            'com_sucursal' => 3,
            'com_nro' => 99,
        ]));
    }
}

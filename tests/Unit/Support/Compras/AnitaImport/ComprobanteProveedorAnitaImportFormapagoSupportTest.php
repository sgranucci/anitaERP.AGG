<?php

namespace Tests\Unit\Support\Compras\AnitaImport;

use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportFormapagoSupport;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorAnitaImportFormapagoSupportTest extends TestCase
{
    public function test_elige_oc_no_efectivo_antes_que_proveedor(): void
    {
        $r = ComprobanteProveedorAnitaImportFormapagoSupport::elegirNoEfectivo(2, 3, 1);

        $this->assertSame(['id' => 2, 'origen' => 'oc'], $r);
    }

    public function test_oc_efectivo_cae_al_proveedor(): void
    {
        $r = ComprobanteProveedorAnitaImportFormapagoSupport::elegirNoEfectivo(1, 3, 1);

        $this->assertSame(['id' => 3, 'origen' => 'proveedor'], $r);
    }

    public function test_sin_oc_usa_proveedor_no_efectivo(): void
    {
        $r = ComprobanteProveedorAnitaImportFormapagoSupport::elegirNoEfectivo(null, 2, 1);

        $this->assertSame(['id' => 2, 'origen' => 'proveedor'], $r);
    }

    public function test_solo_efectivo_no_corrige(): void
    {
        $this->assertNull(
            ComprobanteProveedorAnitaImportFormapagoSupport::elegirNoEfectivo(1, 1, 1)
        );
        $this->assertNull(
            ComprobanteProveedorAnitaImportFormapagoSupport::elegirNoEfectivo(null, null, 1)
        );
        $this->assertNull(
            ComprobanteProveedorAnitaImportFormapagoSupport::elegirNoEfectivo(1, null, 1)
        );
    }
}

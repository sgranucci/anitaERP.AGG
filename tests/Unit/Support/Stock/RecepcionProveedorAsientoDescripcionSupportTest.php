<?php

namespace Tests\Unit\Support\Stock;

use App\Models\Compras\Proveedor;
use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Stock\RecepcionProveedorAsientoDescripcionSupport;
use PHPUnit\Framework\TestCase;

class RecepcionProveedorAsientoDescripcionSupportTest extends TestCase
{
    public function test_descripcion_asiento_erp_incluye_com_y_proveedor(): void
    {
        $recepcion = new Recepcion_Proveedor([
            'numerorecepcion' => 164406,
        ]);
        $recepcion->setRelation('proveedores', new Proveedor(['nombre' => 'Acme S.A.']));

        $this->assertSame(
            'Recepción proveedor #164406 Acme S.A.',
            RecepcionProveedorAsientoDescripcionSupport::descripcionAsientoErp($recepcion)
        );
    }

    public function test_descripcion_ctamov_anita_trunca_a_30_y_sanitiza(): void
    {
        $recepcion = new Recepcion_Proveedor([
            'numerorecepcion' => 164406,
        ]);
        $recepcion->setRelation('proveedores', new Proveedor(['nombre' => 'Proveedor Largo & Cía.']));

        $desc = RecepcionProveedorAsientoDescripcionSupport::descripcionCtamovAnita($recepcion);

        $this->assertLessThanOrEqual(30, strlen($desc));
        $this->assertStringStartsWith('164406 ', $desc);
        $this->assertStringContainsString('Proveedor Largo', $desc);
        $this->assertStringNotContainsString('Rec', $desc);
    }

    public function test_devolucion_nombra_el_com_devuelto(): void
    {
        $origen = new Recepcion_Proveedor([
            'numerorecepcion' => 168684,
            'anita_tipo' => 'COM',
            'anita_letra' => 'X',
            'anita_sucursal' => 1,
            'anita_nro' => 168684,
        ]);
        $recepcion = new Recepcion_Proveedor([
            'numerorecepcion' => 168839,
            'tipo' => Recepcion_Proveedor::TIPO_DEVOLUCION,
            'anita_tipo' => 'COM',
            'anita_letra' => 'X',
            'anita_sucursal' => 1,
            'anita_nro' => 168839,
        ]);
        $recepcion->setRelation('proveedores', new Proveedor(['nombre' => 'GRUPO ALR']));
        $recepcion->setRelation('recepcion_referencia', $origen);

        $this->assertSame(
            'Devolución del COM X0001-00168684. Comprobante COM X0001-00168839 GRUPO ALR',
            RecepcionProveedorAsientoDescripcionSupport::descripcionAsientoErp($recepcion)
        );
        $this->assertSame(
            'Compras · Devolución del COM X0001-00168684',
            RecepcionProveedorAsientoDescripcionSupport::tipoAsientoVisible($recepcion, 'Compras')
        );
        $this->assertSame(
            'Devolución del COM X0001-00168684 · 168839 GRUPO ALR',
            RecepcionProveedorAsientoDescripcionSupport::detalleLineaVisible($recepcion, '168839 GRUPO ALR')
        );

        $ctamov = RecepcionProveedorAsientoDescripcionSupport::descripcionCtamovAnita($recepcion);
        $this->assertLessThanOrEqual(30, strlen($ctamov));
        $this->assertStringStartsWith('DEV 168684 ', $ctamov);
    }

    public function test_sanitizar_ctamov_quita_caracteres_especiales(): void
    {
        $this->assertSame(
            '164406',
            RecepcionProveedorAsientoDescripcionSupport::sanitizarCtamov('164406')
        );
    }
}

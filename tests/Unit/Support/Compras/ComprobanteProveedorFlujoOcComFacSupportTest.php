<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorFlujoOcComFacSupport;
use App\Support\Compras\ComprobanteProveedorModoCarga;
use PHPUnit\Framework\TestCase;

final class ComprobanteProveedorFlujoOcComFacSupportTest extends TestCase
{
    public function test_nc_no_exige_com_aunque_haya_recepcion_en_el_legajo(): void
    {
        $politica = ComprobanteProveedorFlujoOcComFacSupport::resolverPolitica(
            null,
            true,
            '2026-09-10',
            'NC'
        );

        $this->assertTrue($politica['sin_com_por_tipo']);
        $this->assertFalse($politica['debe_asignar_com']);
        $this->assertFalse($politica['bloquea_sin_com']);
        $this->assertSame(
            ComprobanteProveedorModoCarga::SIN_RECEPCION,
            ComprobanteProveedorFlujoOcComFacSupport::modoCargaSugerido($politica, ComprobanteProveedorModoCarga::ASIGNA_RECEPCION)
        );
    }

    public function test_factura_sigue_pidiendo_com_si_hay_disponible(): void
    {
        $politica = ComprobanteProveedorFlujoOcComFacSupport::resolverPolitica(
            null,
            true,
            '2026-09-10',
            'FC'
        );

        $this->assertFalse($politica['sin_com_por_tipo']);
        $this->assertTrue($politica['debe_asignar_com']);
        $this->assertSame(
            ComprobanteProveedorModoCarga::ASIGNA_RECEPCION,
            ComprobanteProveedorFlujoOcComFacSupport::modoCargaSugerido($politica)
        );
    }
}

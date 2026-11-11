<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\IvaVentasListadoFiltros;
use PHPUnit\Framework\TestCase;

final class IvaVentasListadoFiltrosSubdiarioTest extends TestCase
{
    public function test_pasa_subdiario_incluye_factura_t_en_recomendado_y_en_a(): void
    {
        $this->assertTrue(IvaVentasListadoFiltros::pasaSubdiario(
            'T',
            IvaVentasListadoFiltros::SUBDIARIO_VENTAS_A_B
        ));
        $this->assertTrue(IvaVentasListadoFiltros::pasaSubdiario(
            'T',
            IvaVentasListadoFiltros::SUBDIARIO_VENTAS_A
        ));
        $this->assertFalse(IvaVentasListadoFiltros::pasaSubdiario(
            'T',
            IvaVentasListadoFiltros::SUBDIARIO_VENTAS_B
        ));
    }

    public function test_pasa_subdiario_incluye_exportacion_y_m(): void
    {
        foreach (['E', 'M'] as $letra) {
            $this->assertTrue(IvaVentasListadoFiltros::pasaSubdiario(
                $letra,
                IvaVentasListadoFiltros::SUBDIARIO_VENTAS_A_B
            ));
            $this->assertTrue(IvaVentasListadoFiltros::pasaSubdiario(
                $letra,
                IvaVentasListadoFiltros::SUBDIARIO_VENTAS_A
            ));
        }
    }
}

<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorImporteYaFacturadoLegajoSupport;
use App\Support\Compras\ComprobanteProveedorToleranciaImporteSupport;
use PHPUnit\Framework\TestCase;

/**
 * a-compprov.c lee_recepcion: disponible = recepción − ya facturado.
 * Caso OC 223401: COM 1.600.000, 1ª FC 800.000 → 2ª FC 800.000 debe entrar en tolerancia 5%.
 */
class ComprobanteProveedorImporteYaFacturadoLegajoSupportTest extends TestCase
{
    public function test_provision_disponible_descuenta_ya_facturado(): void
    {
        $disponible = ComprobanteProveedorImporteYaFacturadoLegajoSupport::provisionDisponible(1600000.0, 800000.0);
        $this->assertSame(800000.0, $disponible);
    }

    public function test_provision_disponible_no_negativa(): void
    {
        $disponible = ComprobanteProveedorImporteYaFacturadoLegajoSupport::provisionDisponible(100.0, 250.0);
        $this->assertSame(0.0, $disponible);
    }

    public function test_anticipo_mitad_contra_remanente_dentro_de_tolerancia(): void
    {
        $com = 1600000.0;
        $yaFacturado = 800000.0;
        $facturaActual = 800000.0;
        $disponible = ComprobanteProveedorImporteYaFacturadoLegajoSupport::provisionDisponible($com, $yaFacturado);

        $this->assertFalse(
            ComprobanteProveedorToleranciaImporteSupport::excedeTolerancia($facturaActual, $disponible, 5.0)
        );
        $this->assertTrue(
            ComprobanteProveedorToleranciaImporteSupport::excedeTolerancia($facturaActual, $com, 5.0),
            'Sin descontar ya facturado, el mismo caso debe fallar (bug original).'
        );
    }

    public function test_sumar_sin_oc_devuelve_cero(): void
    {
        $suma = ComprobanteProveedorImporteYaFacturadoLegajoSupport::sumarComparableEnLegajo(0);
        $this->assertSame(0.0, $suma['importe']);
        $this->assertSame(0, $suma['cantidad']);
        $this->assertSame([], $suma['items']);
    }
}

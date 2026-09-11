<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorImporteYaFacturadoLegajoSupport;
use App\Support\Compras\ComprobanteProveedorToleranciaImporteSupport;
use PHPUnit\Framework\TestCase;

/**
 * Disponible = COM asignada − ya facturado en esas COM (no todo el legajo).
 * Anticipo 50/50: ambas mitades vinculadas a la misma COM.
 * Telefónica: COM USD convertida a pesos de la factura; otras FC del legajo no restan.
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

    public function test_sumar_sin_recepciones_devuelve_cero(): void
    {
        $suma = ComprobanteProveedorImporteYaFacturadoLegajoSupport::sumarComparableEnRecepciones([]);
        $this->assertSame(0.0, $suma['importe']);
        $this->assertSame(0, $suma['cantidad']);
        $this->assertSame([], $suma['items']);
    }

    public function test_factura_pesos_contra_com_usd_convertida_sin_otras_fc_de_la_com(): void
    {
        // COM USD 280 × 1535 = 429.800 en moneda de la factura (pesos).
        $comEnMonedaFactura = 429800.0;
        $yaFacturadoEnEstasCom = 0.0;
        $disponible = ComprobanteProveedorImporteYaFacturadoLegajoSupport::provisionDisponible(
            $comEnMonedaFactura,
            $yaFacturadoEnEstasCom
        );

        $this->assertFalse(
            ComprobanteProveedorToleranciaImporteSupport::excedeTolerancia(429800.0, $disponible, 5.0)
        );
        $this->assertTrue(
            ComprobanteProveedorToleranciaImporteSupport::excedeTolerancia(
                429800.0,
                ComprobanteProveedorImporteYaFacturadoLegajoSupport::provisionDisponible(429800.0, 1638113.28),
                5.0
            ),
            'Restar todas las FC del legajo deja la COM en 0 (bug Telefónica).'
        );
    }
}

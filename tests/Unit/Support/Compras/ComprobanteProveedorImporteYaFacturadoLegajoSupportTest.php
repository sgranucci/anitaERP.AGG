<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorImporteYaFacturadoLegajoSupport;
use App\Support\Compras\ComprobanteProveedorToleranciaImporteSupport;
use PHPUnit\Framework\TestCase;

/**
 * Dual guard: anticipada 50/50 y Telefónica no se pueden romper el uno al otro.
 *
 * - Anticipada: COM − (FC en COM + anticipadas sin COM del legajo).
 * - Telefónica: COM − solo FC en esas COM (otras FC del legajo no restan).
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

    public function test_anticipo_50_50_primera_sin_com_segunda_contra_remanente(): void
    {
        // OC anticipada: COM 1.600.000; 1ª FC 800k sin pivot; 2ª FC 800k contra COM.
        $com = 1600000.0;
        $enCom = [
            'importe' => 0.0,
            'cantidad' => 0,
            'items' => [],
        ];
        $anticipadaSinCom = [
            'importe' => 800000.0,
            'cantidad' => 1,
            'items' => [[
                'id' => 26121,
                'etiqueta' => 'FNB A 0001-00000454',
                'importe' => 800000.0,
                'signo' => 'S',
            ]],
        ];

        $ya = ComprobanteProveedorImporteYaFacturadoLegajoSupport::fusionarAcumulados($enCom, $anticipadaSinCom);
        $disponible = ComprobanteProveedorImporteYaFacturadoLegajoSupport::provisionDisponible($com, (float) $ya['importe']);

        $this->assertSame(800000.0, (float) $ya['importe']);
        $this->assertSame(800000.0, $disponible);
        $this->assertFalse(
            ComprobanteProveedorToleranciaImporteSupport::excedeTolerancia(800000.0, $disponible, 5.0),
            '2ª mitad 800k vs remanente 800k debe pasar (caso OC 223401).'
        );
        $this->assertTrue(
            ComprobanteProveedorToleranciaImporteSupport::excedeTolerancia(800000.0, $com, 5.0),
            'Sin descontar la anticipada, el mismo caso debe fallar (regresión del 11/9).'
        );
    }

    public function test_telefonica_otras_fc_del_legajo_no_restan_si_no_se_fusionan_anticipadas(): void
    {
        // COM mensual USD convertida; otras FC del legajo van a otras COM (no anticipadas).
        $comEnMonedaFactura = 429800.0;
        $enEstaCom = [
            'importe' => 0.0,
            'cantidad' => 0,
            'items' => [],
        ];
        // Simula “no incluir anticipadas”: solo enCom (flag false en sumarComparableParaProvisionCom).
        $ya = $enEstaCom;
        $disponible = ComprobanteProveedorImporteYaFacturadoLegajoSupport::provisionDisponible(
            $comEnMonedaFactura,
            (float) $ya['importe']
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
            'Restar todas las FC del legajo deja la COM en 0 (bug Telefónica del 8/9).'
        );
    }

    public function test_fusionar_acumulados_no_duplica_mismo_comprobante(): void
    {
        $a = [
            'importe' => 100.0,
            'cantidad' => 1,
            'items' => [['id' => 10, 'etiqueta' => 'A', 'importe' => 100.0, 'signo' => 'S']],
        ];
        $b = [
            'importe' => 100.0,
            'cantidad' => 1,
            'items' => [['id' => 10, 'etiqueta' => 'A', 'importe' => 100.0, 'signo' => 'S']],
        ];

        $merged = ComprobanteProveedorImporteYaFacturadoLegajoSupport::fusionarAcumulados($a, $b);

        $this->assertSame(1, $merged['cantidad']);
        $this->assertSame(100.0, $merged['importe']);
    }

    public function test_sumar_anticipadas_a_por_recepcion_respeta_flag(): void
    {
        $base = [65490 => 0.0];

        $sinFlag = ComprobanteProveedorImporteYaFacturadoLegajoSupport::sumarAnticipadasSinComAPorRecepcion(
            $base,
            0,
            false,
        );
        $this->assertSame([65490 => 0.0], $sinFlag);

        $sinOc = ComprobanteProveedorImporteYaFacturadoLegajoSupport::sumarAnticipadasSinComAPorRecepcion(
            $base,
            0,
            true,
        );
        $this->assertSame([65490 => 0.0], $sinOc);
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

    public function test_para_provision_sin_anticipadas_equivale_a_solo_recepciones(): void
    {
        $soloRecepciones = ComprobanteProveedorImporteYaFacturadoLegajoSupport::sumarComparableEnRecepciones([]);
        $paraProvision = ComprobanteProveedorImporteYaFacturadoLegajoSupport::sumarComparableParaProvisionCom(
            [],
            123,
            false,
        );

        $this->assertSame($soloRecepciones, $paraProvision);
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
}

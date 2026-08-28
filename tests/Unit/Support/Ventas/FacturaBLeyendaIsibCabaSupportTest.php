<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\FacturaBLeyendaIsibCabaSupport as S;
use Tests\TestCase;

/**
 * Test sin tablas de negocio: leyenda ISIB CABA en Factura B.
 */
class FacturaBLeyendaIsibCabaSupportTest extends TestCase
{
    public function test_texto_es_el_pedido_por_agip(): void
    {
        self::assertSame('ALICUOTA ISIB CABA 5%', S::TEXTO);
    }

    public function test_muestra_en_el_bierzo_letra_b(): void
    {
        config()->set('app.empresa', 'EL BIERZO');

        self::assertTrue(S::corresponde(null, 'B'));
        self::assertTrue(S::corresponde(null, 'b'));
        self::assertFalse(S::corresponde(null, 'A'));
        self::assertFalse(S::corresponde(null, null));
    }

    public function test_muestra_en_otra_instalacion_si_el_emisor_es_caba(): void
    {
        config()->set('app.empresa', 'AGG');

        $porJurisdiccion = (object) [
            'puntoventas' => (object) [
                'provincias' => (object) ['jurisdiccion' => 901, 'nombre' => 'Capital Federal'],
            ],
        ];
        $porNombre = (object) [
            'puntoventas' => (object) [
                'empresas' => (object) [
                    'provincia' => (object) ['nombre' => 'Capital Federal'],
                ],
            ],
        ];

        self::assertTrue(S::corresponde($porJurisdiccion, 'B'));
        self::assertTrue(S::corresponde($porNombre, 'B'));
        self::assertFalse(S::corresponde($porJurisdiccion, 'A'));
    }

    public function test_no_muestra_fuera_de_caba(): void
    {
        config()->set('app.empresa', 'AGG');

        $venta = (object) [
            'puntoventas' => (object) [
                'provincias' => (object) ['jurisdiccion' => 902, 'nombre' => 'Buenos Aires'],
            ],
        ];

        self::assertFalse(S::corresponde($venta, 'B'));
        self::assertFalse(S::emisorEsCaba($venta));
    }
}

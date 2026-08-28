<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\ElBierzoFacturaBPercepcionCabaSupport as S;
use Tests\TestCase;

/**
 * Test sin tablas de negocio: solo config EMPRESA + letra.
 */
class ElBierzoFacturaBPercepcionCabaSupportTest extends TestCase
{
    public function test_letra_b_en_el_bierzo_corresponde(): void
    {
        config()->set('app.empresa', 'EL BIERZO');

        self::assertTrue(S::aplicaEnEntorno());
        self::assertTrue(S::correspondePorLetra('B'));
        self::assertTrue(S::correspondePorLetra('b'));
        self::assertFalse(S::correspondePorLetra('A'));
        self::assertFalse(S::correspondePorLetra(null));
    }

    public function test_letra_b_fuera_de_el_bierzo_no_corresponde(): void
    {
        config()->set('app.empresa', 'AGG');

        self::assertFalse(S::aplicaEnEntorno());
        self::assertFalse(S::correspondePorLetra('B'));
        self::assertFalse(S::debeForzarDesdeCliente([S::FLAG => true]));
    }

    public function test_flag_cliente_solo_en_el_bierzo(): void
    {
        config()->set('app.empresa', 'EL BIERZO');

        self::assertTrue(S::debeForzarDesdeCliente([S::FLAG => true]));
        self::assertFalse(S::debeForzarDesdeCliente([]));
        self::assertFalse(S::debeForzarDesdeCliente(['omitir_percepciones' => true]));
    }

    public function test_jurisdiccion_caba_es_901(): void
    {
        self::assertSame(901, S::JURISDICCION);
        self::assertTrue(S::esJurisdiccionCaba(901));
        self::assertTrue(S::esJurisdiccionCaba('901'));
        self::assertFalse(S::esJurisdiccionCaba(902));
    }
}

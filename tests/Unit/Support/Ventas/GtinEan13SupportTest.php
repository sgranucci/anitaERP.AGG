<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\GtinEan13Support;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD). Dígito verificador GS1 de los GTIN seed de Anita.
 */
class GtinEan13SupportTest extends TestCase
{
    public function test_acepta_los_gtin_de_anita(): void
    {
        self::assertTrue(GtinEan13Support::esValido('7790001001030'));
        self::assertTrue(GtinEan13Support::esValido('7790001001047'));
        self::assertTrue(GtinEan13Support::esValido('7790001001061'));
        self::assertTrue(GtinEan13Support::esValido('7790001001085'));
    }

    public function test_rechaza_placeholder_y_corto(): void
    {
        self::assertFalse(GtinEan13Support::esValido('7790000000000'));
        self::assertTrue(GtinEan13Support::esAceptable('7790000000000'));
        self::assertFalse(GtinEan13Support::esValido('7790001001031'));
        self::assertFalse(GtinEan13Support::esValido('123'));
        self::assertFalse(GtinEan13Support::esValido(''));
        self::assertFalse(GtinEan13Support::esValido(null));
        self::assertNull(GtinEan13Support::normalizar('0'));
    }

    public function test_calcula_el_digito(): void
    {
        self::assertSame(0, GtinEan13Support::digitoVerificador('779000100103'));
        self::assertSame(5, GtinEan13Support::digitoVerificador('779000100108'));
    }
}

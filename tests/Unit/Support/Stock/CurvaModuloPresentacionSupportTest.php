<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\CurvaModuloPresentacionSupport as S;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD): curva de un módulo y cantidad de módulos.
 */
class CurvaModuloPresentacionSupportTest extends TestCase
{
    /** @var array<int, array<int, int>> */
    private array $catalogo = [
        1 => [35 => 1, 36 => 2, 37 => 3, 38 => 3, 39 => 2, 40 => 1],
        28 => [36 => 2, 37 => 3, 38 => 3, 39 => 2, 40 => 2],
        35 => [35 => 2, 36 => 2, 37 => 2, 38 => 2, 39 => 2, 40 => 2],
    ];

    public function test_dos_modulos_12d_muestran_la_curva_y_la_cantidad(): void
    {
        $r = S::presentar([
            36 => 4, 37 => 6, 38 => 6, 39 => 4, 40 => 4,
        ], $this->catalogo);

        self::assertTrue($r['coincide']);
        self::assertSame(2, $r['modulos']);
        self::assertSame(12, $r['pares_modulo']);
        self::assertSame(24, $r['total']);
        self::assertSame([36 => 2, 37 => 3, 38 => 3, 39 => 2, 40 => 2], $r['medidas']);
    }

    public function test_curva_que_no_es_modulo_queda_entera_con_un_modulo(): void
    {
        $r = S::presentar([38 => 6, 39 => 4, 40 => 2], $this->catalogo);

        self::assertFalse($r['coincide']);
        self::assertSame(1, $r['modulos']);
        self::assertSame(12, $r['pares_modulo']);
        self::assertSame(12, $r['total']);
        self::assertSame([38 => 6, 39 => 4, 40 => 2], $r['medidas']);
    }

    public function test_modulo_con_pares_repetidos_se_parte_por_la_curva_del_catalogo(): void
    {
        $r = S::presentar([
            35 => 4, 36 => 4, 37 => 4, 38 => 4, 39 => 4, 40 => 4,
        ], $this->catalogo);

        self::assertTrue($r['coincide']);
        self::assertSame(2, $r['modulos']);
        self::assertSame([35 => 2, 36 => 2, 37 => 2, 38 => 2, 39 => 2, 40 => 2], $r['medidas']);
        self::assertSame(12, $r['pares_modulo']);
        self::assertSame(24, $r['total']);
    }

    public function test_curva_repetida_de_12_pares_que_no_esta_en_el_catalogo(): void
    {
        $r = S::presentar([
            39 => 6, 40 => 12, 41 => 18, 42 => 12, 43 => 12, 44 => 6, 45 => 6,
        ], $this->catalogo);

        self::assertTrue($r['coincide']);
        self::assertSame(6, $r['modulos']);
        self::assertSame(12, $r['pares_modulo']);
        self::assertSame(72, $r['total']);
        self::assertSame([
            39 => 1, 40 => 2, 41 => 3, 42 => 2, 43 => 2, 44 => 1, 45 => 1,
        ], $r['medidas']);
    }

    public function test_un_solo_modulo_no_cambia_los_talles(): void
    {
        $curva = [35 => 1, 36 => 2, 37 => 3, 38 => 3, 39 => 2, 40 => 1];
        $r = S::presentar($curva, $this->catalogo);

        self::assertTrue($r['coincide']);
        self::assertSame(1, $r['modulos']);
        self::assertSame($curva, $r['medidas']);
        self::assertSame(12, $r['total']);
    }
}

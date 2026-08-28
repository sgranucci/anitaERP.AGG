<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\FacturaPdfIdentificacionSupport as S;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD): título y código AFIP del PDF según el comprobante grabado.
 */
final class FacturaPdfIdentificacionSupportTest extends TestCase
{
    public function test_factura_b_no_se_imprime_como_fce(): void
    {
        $ident = S::desdeVenta((object) [
            'codigo' => 'FAC B-00008-00000044',
            'codigo_afip' => 6,
            'tipotransacciones' => (object) ['codigo' => '001', 'nombre' => 'Factura Electronica MiPyme'],
        ]);

        self::assertSame('B', $ident['letra']);
        self::assertSame(6, $ident['codigo_afip']);
        self::assertSame('006', $ident['codigo_afip_pad']);
        self::assertSame('FACTURA', $ident['nombre']);
        self::assertFalse($ident['es_fce']);
    }

    public function test_fce_b_si_el_codigo_grabado_es_fce(): void
    {
        $ident = S::desdeVenta((object) [
            'codigo' => 'FCE B-00008-00000045',
            'codigo_afip' => 6,
            'tipotransacciones' => (object) ['codigo' => '001', 'nombre' => 'Factura'],
        ]);

        self::assertSame(206, $ident['codigo_afip']);
        self::assertSame('FACTURA DE CREDITO ELECTRONICA', $ident['nombre']);
        self::assertTrue($ident['es_fce']);
    }

    public function test_codigo_afip_206_con_prefijo_fac_queda_factura_b(): void
    {
        $ident = S::desdeVenta((object) [
            'codigo' => 'FAC B-00008-00000044',
            'codigo_afip' => 206,
            'tipotransacciones' => (object) ['codigo' => '201', 'nombre' => 'Factura Electronica MiPyme'],
        ]);

        self::assertSame(6, $ident['codigo_afip']);
        self::assertSame('FACTURA', $ident['nombre']);
        self::assertFalse($ident['es_fce']);
    }
}

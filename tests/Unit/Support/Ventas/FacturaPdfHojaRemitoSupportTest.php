<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\FacturaPdfHojaRemitoSupport as S;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD): cuándo el PDF de factura incluye hoja remito.
 */
final class FacturaPdfHojaRemitoSupportTest extends TestCase
{
    public function test_ferli_local_o_tiendanube_sin_hoja_remito(): void
    {
        $venta = (object) ['numeroremito' => 0, 'remito_id' => null];

        self::assertFalse(S::mostrar($venta, false, false, false, true, true));
    }

    public function test_ferli_mayorista_con_remito_incluye_hoja(): void
    {
        $venta = (object) ['numeroremito' => 83092, 'remito_id' => 46];

        self::assertTrue(S::mostrar($venta, false, false, false, true, false));
    }

    public function test_ferli_sin_remito_asociado_no_inventa_hoja(): void
    {
        $venta = (object) ['numeroremito' => 0, 'remito_id' => null];

        self::assertFalse(S::mostrar($venta, false, false, false, true, false));
    }

    public function test_elbierzo_sigue_incluyendo_hoja_salvo_omitir(): void
    {
        $venta = (object) ['numeroremito' => 0, 'remito_id' => null];

        self::assertTrue(S::mostrar($venta, false, false, true, false, false));
        self::assertFalse(S::mostrar($venta, true, false, true, false, false));
    }

    public function test_solo_remito_siempre_muestra_hoja(): void
    {
        $venta = (object) ['numeroremito' => 0, 'remito_id' => null];

        self::assertTrue(S::mostrar($venta, true, true, false, true, true));
    }
}

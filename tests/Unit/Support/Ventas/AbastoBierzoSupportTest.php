<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\AbastoBierzoSupport;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD). Contrasta a-comprob.c: tot_abasto = tot_kilo * tasa.
 * tot_kilo incluye renglones a precio 0; excluye SKU 903 y unidad CAJ.
 */
final class AbastoBierzoSupportTest extends TestCase
{
    public function test_incluye_renglones_bonificados_y_excluye_caja_y_flete(): void
    {
        $items = [
            ['sku' => '331', 'cantidad' => 163.2, 'unidad_medida' => 'KG', 'precio' => 9556.01],
            ['sku' => '331', 'cantidad' => 32.6, 'unidad_medida' => 'KG', 'precio' => 0],
            ['sku' => '903', 'cantidad' => 10, 'precio' => 100],
            ['sku' => '430', 'cantidad' => 5, 'unidad_medida' => 'CAJ', 'precio' => 10],
        ];

        $this->assertSame(195.8, AbastoBierzoSupport::kilosDesdeItems($items));
    }

    public function test_cinco_saltos_danil_1854(): void
    {
        $kilos = 1197.5;
        $this->assertSame(77837.5, AbastoBierzoSupport::importe($kilos, 65));
    }

    public function test_cero_si_no_hay_kilos_ni_tasa(): void
    {
        $this->assertSame(0.0, AbastoBierzoSupport::importe(100, 0));
        $this->assertSame(0.0, AbastoBierzoSupport::importe(0, 65));
    }
}

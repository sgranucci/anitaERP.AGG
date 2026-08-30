<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\RemitoFormularioLeyendaSupport;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD): partición 4 × 40 de l-comprobQR / a-comprob.c.
 */
final class RemitoFormularioLeyendaSupportTest extends TestCase
{
    public function test_vacio_devuelve_cuatro_lineas_vacias(): void
    {
        $p = RemitoFormularioLeyendaSupport::partir('   ');

        $this->assertSame('', $p['leyenda1']);
        $this->assertSame('', $p['leyenda2']);
        $this->assertSame('', $p['leyenda3']);
        $this->assertSame('', $p['leyenda']);
    }

    public function test_una_linea_corta_va_a_leyenda1(): void
    {
        $p = RemitoFormularioLeyendaSupport::partir('BONIFICACION cliente');

        $this->assertSame('BONIFICACION cliente', $p['leyenda1']);
        $this->assertSame('', $p['leyenda2']);
        $this->assertSame('', $p['leyenda3']);
        $this->assertSame('', $p['leyenda']);
    }

    public function test_corta_en_40_y_el_resto_pasa_a_la_siguiente(): void
    {
        $texto = str_repeat('A', 40).str_repeat('B', 40).str_repeat('C', 40).str_repeat('D', 15);
        $p = RemitoFormularioLeyendaSupport::partir($texto);

        $this->assertSame(str_repeat('A', 40), $p['leyenda1']);
        $this->assertSame(str_repeat('B', 40), $p['leyenda2']);
        $this->assertSame(str_repeat('C', 40), $p['leyenda3']);
        $this->assertSame(str_repeat('D', 15), $p['leyenda']);
    }

    public function test_saltos_de_linea_ocupan_slots(): void
    {
        $p = RemitoFormularioLeyendaSupport::partir("linea uno\nlinea dos\nlinea tres\nobservaciones");

        $this->assertSame('linea uno', $p['leyenda1']);
        $this->assertSame('linea dos', $p['leyenda2']);
        $this->assertSame('linea tres', $p['leyenda3']);
        $this->assertSame('observaciones', $p['leyenda']);
    }

    public function test_comp_leyenda_son_160_caracteres_fijos(): void
    {
        $buf = RemitoFormularioLeyendaSupport::paraCompLeyenda("uno\ndos");

        $this->assertSame(160, strlen($buf));
        $this->assertSame('uno', rtrim(substr($buf, 0, 40)));
        $this->assertSame('dos', rtrim(substr($buf, 40, 40)));
        $this->assertSame('', rtrim(substr($buf, 80, 40)));
        $this->assertSame('', rtrim(substr($buf, 120, 40)));
    }

    public function test_desde_venta_prefiere_leyenda_de_factura(): void
    {
        $venta = (object) [
            'leyenda' => 'Leyenda factura',
            'remitos' => (object) ['leyenda' => 'Leyenda remito'],
        ];
        $p = RemitoFormularioLeyendaSupport::desdeVenta($venta);

        $this->assertSame('Leyenda factura', $p['leyenda1']);
    }

    public function test_desde_venta_usa_remito_si_factura_no_tiene(): void
    {
        $venta = (object) [
            'leyenda' => '',
            'remitos' => (object) ['leyenda' => 'Solo remito'],
        ];
        $p = RemitoFormularioLeyendaSupport::desdeVenta($venta);

        $this->assertSame('Solo remito', $p['leyenda1']);
    }
}

<?php

namespace Tests\Unit\Support\Contable;

use App\Support\Contable\AsientoAnitaMetadatosSupport;
use PHPUnit\Framework\TestCase;

class AsientoAnitaMetadatosSupportTest extends TestCase
{
    public function test_comprobante_visible_parsea_codigo_venta_erp_con_guiones(): void
    {
        $resultado = AsientoAnitaMetadatosSupport::comprobanteVisible(
            'FSL B-00201-00001301 Tragamone'
        );

        $this->assertSame('FSL', $resultado['tipo']);
        $this->assertSame('B', $resultado['letra']);
        $this->assertSame(201, $resultado['sucursal']);
        $this->assertSame(1301, $resultado['nro']);
    }

    public function test_comprobante_visible_parsea_fac_con_guiones(): void
    {
        $resultado = AsientoAnitaMetadatosSupport::comprobanteVisible(
            'FAC B-00008-00000044'
        );

        $this->assertSame('FAC', $resultado['tipo']);
        $this->assertSame('B', $resultado['letra']);
        $this->assertSame(8, $resultado['sucursal']);
        $this->assertSame(44, $resultado['nro']);
    }

    public function test_comprobante_visible_no_toma_nombre_de_cliente_como_tip(): void
    {
        $resultado = AsientoAnitaMetadatosSupport::comprobanteVisible(
            'ERASUN, NORMA NELIDA - Cobranza Nro. 77722'
        );

        $this->assertSame('', $resultado['tipo']);
        $this->assertSame(0, $resultado['nro']);
    }

    public function test_comprobante_visible_sigue_entendiendo_subdiario(): void
    {
        $resultado = AsientoAnitaMetadatosSupport::comprobanteVisible(
            '[SUBD] T COB A 1 77384'
        );

        $this->assertSame('T', $resultado['sistema']);
        $this->assertSame('COB', $resultado['tipo']);
        $this->assertSame(77384, $resultado['nro']);
    }
}

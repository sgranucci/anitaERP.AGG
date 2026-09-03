<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\OrdencompraLegajoFacturaArcaSupport;
use PHPUnit\Framework\TestCase;

class OrdencompraLegajoFacturaArcaSupportTest extends TestCase
{
    public function test_letra_b_suma_cinco_al_tipo(): void
    {
        $this->assertSame(6, OrdencompraLegajoFacturaArcaSupport::codigoArcaEfectivo('001', 'B'));
        $this->assertSame('006', OrdencompraLegajoFacturaArcaSupport::codigoArcaPad('001', 'B'));
    }

    public function test_letra_c_suma_diez(): void
    {
        $this->assertSame(11, OrdencompraLegajoFacturaArcaSupport::codigoArcaEfectivo(1, 'C'));
        $this->assertSame('011', OrdencompraLegajoFacturaArcaSupport::codigoArcaPad('001', 'c'));
    }

    public function test_letra_a_conserva_tipo(): void
    {
        $this->assertSame(1, OrdencompraLegajoFacturaArcaSupport::codigoArcaEfectivo('001', 'A'));
        $this->assertSame(2, OrdencompraLegajoFacturaArcaSupport::codigoArcaEfectivo('002', 'A'));
    }

    public function test_tipo_ya_serie_b_no_vuelve_a_sumar(): void
    {
        $this->assertSame(6, OrdencompraLegajoFacturaArcaSupport::codigoArcaEfectivo('006', 'B'));
        $this->assertSame(11, OrdencompraLegajoFacturaArcaSupport::codigoArcaEfectivo('011', 'C'));
    }
}

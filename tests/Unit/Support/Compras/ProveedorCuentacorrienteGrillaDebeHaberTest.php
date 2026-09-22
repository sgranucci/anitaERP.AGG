<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ProveedorCuentacorrienteGrillaSupport;
use PHPUnit\Framework\TestCase;

class ProveedorCuentacorrienteGrillaDebeHaberTest extends TestCase
{
    public function test_factura_va_al_haber(): void
    {
        $dh = ProveedorCuentacorrienteGrillaSupport::debeHaberDesdeTotal(1500.50);

        $this->assertNull($dh['debe']);
        $this->assertEqualsWithDelta(1500.50, $dh['haber'], 0.0001);
    }

    public function test_sufijo_cuota_solo_si_hay_mas_de_una(): void
    {
        $this->assertSame(' (1/2)', ProveedorCuentacorrienteGrillaSupport::formatearSufijoCuota(1, 2));
        $this->assertSame(' (2/2)', ProveedorCuentacorrienteGrillaSupport::formatearSufijoCuota(2, 2));
        $this->assertSame('', ProveedorCuentacorrienteGrillaSupport::formatearSufijoCuota(1, 1));
        $this->assertSame('', ProveedorCuentacorrienteGrillaSupport::formatearSufijoCuota(0, 2));
        $this->assertSame('', ProveedorCuentacorrienteGrillaSupport::formatearSufijoCuota(1, 0));
    }

    public function test_opp_y_nc_van_al_debe(): void
    {
        $opp = ProveedorCuentacorrienteGrillaSupport::debeHaberDesdeTotal(-800.0);
        $this->assertEqualsWithDelta(800.0, $opp['debe'], 0.0001);
        $this->assertNull($opp['haber']);

        $nc = ProveedorCuentacorrienteGrillaSupport::debeHaberDesdeTotal(-125.25);
        $this->assertEqualsWithDelta(125.25, $nc['debe'], 0.0001);
        $this->assertNull($nc['haber']);
    }

    public function test_importe_absoluto_explicito(): void
    {
        $dh = ProveedorCuentacorrienteGrillaSupport::debeHaberDesdeTotal(-100.0, 250.0);

        $this->assertEqualsWithDelta(250.0, $dh['debe'], 0.0001);
        $this->assertNull($dh['haber']);
    }

    public function test_cero_no_muestra_columnas(): void
    {
        $dh = ProveedorCuentacorrienteGrillaSupport::debeHaberDesdeTotal(0.0);

        $this->assertNull($dh['debe']);
        $this->assertNull($dh['haber']);
    }
}

<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorCupoNcLegajoSupport;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorCupoNcLegajoSupportTest extends TestCase
{
    public function test_exceso_sobre_provision(): void
    {
        $this->assertSame(11258.24, ComprobanteProveedorCupoNcLegajoSupport::excesoSobreProvision(109296.54, 98038.30));
        $this->assertSame(0.0, ComprobanteProveedorCupoNcLegajoSupport::excesoSobreProvision(98038.30, 109296.54));
        $this->assertSame(0.0, ComprobanteProveedorCupoNcLegajoSupport::excesoSobreProvision(100.0, 100.0));
    }

    public function test_aplicar_cupo_parcial_y_total(): void
    {
        $parcial = ComprobanteProveedorCupoNcLegajoSupport::aplicarCupo(11258.24, 5000.0);
        $this->assertSame(5000.0, $parcial['aplicado']);
        $this->assertSame(6258.24, $parcial['residual']);
        $this->assertSame(0.0, $parcial['cupo_restante']);

        $total = ComprobanteProveedorCupoNcLegajoSupport::aplicarCupo(11258.24, 12000.0);
        $this->assertSame(11258.24, $total['aplicado']);
        $this->assertSame(0.0, $total['residual']);
        $this->assertSame(741.76, $total['cupo_restante']);
    }

    /** Caso captura: FC 109296.54 vs COM 98038.30 (~11,5%) con NC que cubre el exceso. */
    public function test_dentro_de_tolerancia_tras_cupo_nc_caso_captura(): void
    {
        $this->assertFalse(ComprobanteProveedorCupoNcLegajoSupport::dentroDeToleranciaTrasCupoNc(
            109296.54,
            98038.30,
            0.0,
            5.0,
        ));

        $this->assertTrue(ComprobanteProveedorCupoNcLegajoSupport::dentroDeToleranciaTrasCupoNc(
            109296.54,
            98038.30,
            11258.24,
            5.0,
        ));
    }

    public function test_nc_parcial_deja_residual_fuera_de_tolerancia(): void
    {
        // Exceso 11258; NC solo 1000 → residual efectivo sigue ~10%.
        $this->assertFalse(ComprobanteProveedorCupoNcLegajoSupport::dentroDeToleranciaTrasCupoNc(
            109296.54,
            98038.30,
            1000.0,
            5.0,
        ));
    }

    public function test_consumir_cupo_entre_dos_excesos(): void
    {
        $r = ComprobanteProveedorCupoNcLegajoSupport::consumirCupoContraExcesos(
            10000.0,
            [
                20 => 8000.0,
                10 => 5000.0,
            ],
        );

        // Orden estable por clave string: "10" antes que "20".
        $this->assertSame(5000.0, $r['efectivos_reduccion'][10]);
        $this->assertSame(5000.0, $r['efectivos_reduccion'][20]);
        $this->assertSame(0.0, $r['cupo_restante']);
    }

    public function test_asiento_permite_si_nc_cubre_fuera_de_banda(): void
    {
        // 11.5% sobre COM: fuera del 5%; NC cubre la parte fuera de banda.
        $this->assertTrue(ComprobanteProveedorCupoNcLegajoSupport::diferenciaAsientoPermitidaConCupoNc(
            11258.24,
            98038.30,
            11258.24,
        ));

        $this->assertFalse(ComprobanteProveedorCupoNcLegajoSupport::diferenciaAsientoPermitidaConCupoNc(
            11258.24,
            98038.30,
            0.0,
        ));

        // Dentro del 5% sin NC.
        $this->assertTrue(ComprobanteProveedorCupoNcLegajoSupport::diferenciaAsientoPermitidaConCupoNc(
            4000.0,
            98038.30,
            0.0,
        ));

        // Defecto (FC < COM) no se cubre con NC.
        $this->assertFalse(ComprobanteProveedorCupoNcLegajoSupport::diferenciaAsientoPermitidaConCupoNc(
            -11258.24,
            98038.30,
            20000.0,
        ));
    }

    public function test_asiento_permite_si_nc_cubre_solo_la_parte_sobre_5_pct(): void
    {
        // Banda 5% de 98038.30 ≈ 4901.92; fuera de banda ≈ 6356.32.
        $this->assertTrue(ComprobanteProveedorCupoNcLegajoSupport::diferenciaAsientoPermitidaConCupoNc(
            11258.24,
            98038.30,
            6356.32,
        ));

        $this->assertFalse(ComprobanteProveedorCupoNcLegajoSupport::diferenciaAsientoPermitidaConCupoNc(
            11258.24,
            98038.30,
            6000.0,
        ));
    }
}

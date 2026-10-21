<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Caja;

use App\Models\Caja\Tipotransaccion_Caja;
use App\Support\Caja\IngresoEgresoCajaMontoSignoSupport;
use PHPUnit\Framework\TestCase;

class IngresoEgresoCajaMontoSignoSupportTest extends TestCase
{
    private function tipo(string $abrev, string $operacion, string $signo): Tipotransaccion_Caja
    {
        return new Tipotransaccion_Caja([
            'abreviatura' => $abrev,
            'operacion' => $operacion,
            'signo' => $signo,
        ]);
    }

    public function test_egreso_form_positivo_o_negativo_persiste_negativo(): void
    {
        $egr = $this->tipo('EGR', 'E', 'E');

        $this->assertSame(-100.0, IngresoEgresoCajaMontoSignoSupport::aBaseDatos(100.0, $egr));
        $this->assertSame(-100.0, IngresoEgresoCajaMontoSignoSupport::aBaseDatos(-100.0, $egr));
        $this->assertSame(
            -100.0,
            IngresoEgresoCajaMontoSignoSupport::importeFirmadoParaAsiento(-100.0, $egr)
        );
    }

    public function test_ingreso_form_positivo_o_negativo_persiste_positivo(): void
    {
        $ing = $this->tipo('ING', 'I', 'I');

        $this->assertSame(100.0, IngresoEgresoCajaMontoSignoSupport::aBaseDatos(100.0, $ing));
        $this->assertSame(100.0, IngresoEgresoCajaMontoSignoSupport::aBaseDatos(-100.0, $ing));
    }

    public function test_transferencia_conserva_signo_del_formulario(): void
    {
        $tra = $this->tipo('TRA', 'T', 'I');

        $this->assertSame(50.0, IngresoEgresoCajaMontoSignoSupport::aBaseDatos(50.0, $tra));
        $this->assertSame(-50.0, IngresoEgresoCajaMontoSignoSupport::aBaseDatos(-50.0, $tra));
        $this->assertSame(-50.0, IngresoEgresoCajaMontoSignoSupport::aFormulario(-50.0, $tra));
    }

    public function test_anulacion_invierte_el_monto_grabado_sin_refirmar_el_tipo(): void
    {
        $this->assertSame(800000.0, IngresoEgresoCajaMontoSignoSupport::montoAnulacion(-800000.0));
        $this->assertSame(-9000000.0, IngresoEgresoCajaMontoSignoSupport::montoAnulacion(9000000.0));
    }

    public function test_formulario_ie_siempre_absoluto(): void
    {
        $egr = $this->tipo('EGR', 'E', 'E');
        $ing = $this->tipo('ING', 'I', 'I');

        $this->assertSame(100.0, IngresoEgresoCajaMontoSignoSupport::aFormulario(-100.0, $egr));
        $this->assertSame(100.0, IngresoEgresoCajaMontoSignoSupport::aFormulario(100.0, $egr));
        $this->assertSame(100.0, IngresoEgresoCajaMontoSignoSupport::aFormulario(100.0, $ing));
    }
}

<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\ChequePropioAnitaNumeracionSupport;
use PHPUnit\Framework\TestCase;

class ChequePropioAnitaNumeracionSupportTest extends TestCase
{
    public function test_imputacion_desde_codigo_pad_8(): void
    {
        $this->assertSame('00000127', ChequePropioAnitaNumeracionSupport::imputacionDesdeCodigo('127'));
        $this->assertSame('00000127', ChequePropioAnitaNumeracionSupport::imputacionDesdeCodigo('00000127'));
    }

    public function test_tctes_000_no_es_cheque(): void
    {
        $this->assertFalse(ChequePropioAnitaNumeracionSupport::esTctesCheque('000'));
        $this->assertFalse(ChequePropioAnitaNumeracionSupport::esTctesCheque(''));
        $this->assertTrue(ChequePropioAnitaNumeracionSupport::esTctesCheque('324'));
        $this->assertTrue(ChequePropioAnitaNumeracionSupport::esTctesCheque('24'));
    }

    public function test_detecta_descripcion_diferido(): void
    {
        $this->assertTrue(ChequePropioAnitaNumeracionSupport::esDescripcionDiferido('MACRO BIYEMAS CH.DIFERIDO', 'BMD'));
        $this->assertFalse(ChequePropioAnitaNumeracionSupport::esDescripcionDiferido('MACRO BIYEMAS CHEQUE', 'BMC'));
    }

    public function test_fecha_pago_posterior_es_diferido(): void
    {
        $this->assertTrue(ChequePropioAnitaNumeracionSupport::esFechaDiferida('2026-09-10', '2026-10-09'));
        $this->assertFalse(ChequePropioAnitaNumeracionSupport::esFechaDiferida('2026-09-10', '2026-09-10'));
    }

    public function test_elige_tctes_diferido_si_fecha_posterior(): void
    {
        $filas = [
            ['clave' => 'BMC', 'desc' => 'CHEQUE AL DIA', 'numero' => 320, 'diferido' => false],
            ['clave' => 'BMD', 'desc' => 'CH.DIFERIDO', 'numero' => 324, 'diferido' => true],
        ];

        $elegido = ChequePropioAnitaNumeracionSupport::elegirTctes($filas, true);
        $this->assertSame('BMD', $elegido['clave']);
        $this->assertSame(324, $elegido['numero']);

        $alDia = ChequePropioAnitaNumeracionSupport::elegirTctes($filas, false);
        $this->assertSame('BMC', $alDia['clave']);
    }

    public function test_elige_unico_tctes_si_no_hay_match(): void
    {
        $filas = [
            ['clave' => 'BMD', 'desc' => 'CH.DIFERIDO', 'numero' => 324, 'diferido' => true],
        ];
        $this->assertSame('BMD', ChequePropioAnitaNumeracionSupport::elegirTctes($filas, false)['clave']);
        $this->assertNull(ChequePropioAnitaNumeracionSupport::elegirTctes([], true));
    }
}

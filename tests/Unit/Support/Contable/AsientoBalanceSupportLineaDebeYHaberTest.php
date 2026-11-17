<?php

namespace Tests\Unit\Support\Contable;

use App\Support\Contable\AsientoBalanceSupport;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Incidente Ferli OPP 46358: Debe+Haber en la misma línea engañaba el balance.
 */
class AsientoBalanceSupportLineaDebeYHaberTest extends TestCase
{
    public function test_detecta_linea_con_debe_y_haber(): void
    {
        $indices = AsientoBalanceSupport::indicesLineasConDebeYHaber(
            ['', 17156311.61, 323501.88],
            [26860325.59, '', 0.01],
        );

        $this->assertSame([2], $indices);
    }

    public function test_totales_no_quedan_balanceados_si_hay_ambos_lados(): void
    {
        // Mismos importes que OPP 46358: el total "cierra" pero la línea 7 tiene ambos.
        $totales = AsientoBalanceSupport::totalesDesdeDebeHaber(
            [0, 17156311.61, 245112.41, 343126.22, 3602825.43, 5189448.05, 323501.88],
            [26860325.59, 0, 0, 0, 0, 0, 0.01],
        );

        $this->assertSame([6], $totales['lineas_debe_y_haber']);
        $this->assertFalse($totales['balanceado']);
        $this->assertEqualsWithDelta(0.0, $totales['diferencia'], 0.001);
    }

    public function test_assert_rechaza_payload_con_debe_y_haber_aunque_totales_cierren(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Debe y en Haber/');

        AsientoBalanceSupport::assertBalanceadoDesdePayload([
            'debes' => [0, 100.0, 50.01],
            'haberes' => [150.0, 0, 0.01],
            'moneda_ids' => [1, 1, 1],
            'cotizaciones' => [1, 1, 1],
        ]);
    }

    public function test_assert_acepta_asiento_balanceado_sin_ambos_lados(): void
    {
        AsientoBalanceSupport::assertBalanceadoDesdePayload([
            'debes' => [150.0, 0],
            'haberes' => [0, 150.0],
            'moneda_ids' => [1, 1],
            'cotizaciones' => [1, 1],
        ]);

        $this->addToAssertionCount(1);
    }

    public function test_acepta_claves_debeasientos_de_ingreso_egreso(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AsientoBalanceSupport::assertLineasSinDebeYHaber([
            'debeasientos' => ['10,00', '5,00'],
            'haberasientos' => ['', '0,01'],
        ]);
    }
}

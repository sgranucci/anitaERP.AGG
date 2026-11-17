<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorMonedaMotor;
use PHPUnit\Framework\TestCase;

/**
 * OC 225541 (ENERGY SAVER): OC en USD, factura en pesos al mismo tipo.
 * Sin convertir, el tope compara 1.168.315,50 $ vs 744,15 USD → falso positivo.
 */
class ComprobanteProveedorAnticipoPctMonedaTopeTest extends TestCase
{
    public function test_factura_pesos_equivale_al_total_oc_usd(): void
    {
        // 100 × USD 6,15 = 615 neto + 21% IVA = 744,15 USD × cot 1570 = 1.168.315,50 ARS
        $totalOcUsd = 744.15;
        $totalFacturaArs = 1168315.50;
        $cotizacion = 1570.0;

        $enUsd = ComprobanteProveedorMonedaMotor::convertir(
            $totalFacturaArs,
            1,
            $cotizacion,
            '2026-11-16',
            2,
            $cotizacion,
            '2026-11-16',
            'factura en pesos',
            'total OC USD',
        );

        $this->assertEqualsWithDelta($totalOcUsd, $enUsd, 0.01);

        $tol = 1.0;
        $tope = round($totalOcUsd * (1 + $tol / 100.0), 2);
        $this->assertTrue($enUsd <= $tope + 0.05);
    }

    public function test_sin_conversion_dispara_falso_exceso(): void
    {
        $totalOcUsd = 744.15;
        $totalFacturaArs = 1168315.50;
        $tope = round($totalOcUsd * 1.01, 2);

        $this->assertFalse($totalFacturaArs <= $tope + 0.05);
    }

    public function test_misma_moneda_no_altera_importe(): void
    {
        $importe = 744.15;
        $out = ComprobanteProveedorMonedaMotor::convertir(
            $importe,
            2,
            1570.0,
            '2026-11-16',
            2,
            1400.0,
            '2026-11-16',
            'fc',
            'oc',
        );

        $this->assertSame($importe, $out);
    }
}

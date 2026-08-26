<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\Flash\FlashCajaDropWinFormulaSupport;
use Tests\TestCase;

class FlashCajaDropWinFormulaSupportTest extends TestCase
{
    public function test_slot_d_reemplaza_tickets_e_impuesto_venta_por_venta_ficha(): void
    {
        // Bill 201.629.600 + venta fichas 227.597.995,88 + QR 79.053.541,80 − imp drop 1.895.729,66
        $this->assertSame(
            506385408.02,
            FlashCajaDropWinFormulaSupport::slotD(201629600.00, 227597995.88, 79053541.80, 1895729.66)
        );
    }

    public function test_slot_r_resta_pagos_e_impuesto_drop_sin_tickets_ni_impuesto_venta(): void
    {
        $this->assertSame(
            100.00,
            FlashCajaDropWinFormulaSupport::slotR(50.00, 80.00, 10.00, 20.00, 5.00, 10.00, 5.00)
        );
    }

    public function test_rul_d_es_solo_bill(): void
    {
        $this->assertSame(1987000.00, FlashCajaDropWinFormulaSupport::rulD(1987000.00));
    }

    public function test_rul_r_es_bill_menos_pagos_sin_ventas_tickets(): void
    {
        $this->assertSame(80.00, FlashCajaDropWinFormulaSupport::rulR(100.00, 20.00));
    }

    public function test_venta_ficha_prefiere_rendicion_c(): void
    {
        $this->assertSame(
            227597995.88,
            FlashCajaDropWinFormulaSupport::resolverVentaFicha(
                ['origen' => 'anita', 'venta_ficha' => 227597995.88],
                999.00
            )
        );
    }

    public function test_venta_ficha_cae_a_wigos_si_no_hay_turno_c(): void
    {
        $this->assertSame(
            123.45,
            FlashCajaDropWinFormulaSupport::resolverVentaFicha(
                ['origen' => 'ninguno', 'venta_ficha' => 0.0],
                123.45
            )
        );
    }
}

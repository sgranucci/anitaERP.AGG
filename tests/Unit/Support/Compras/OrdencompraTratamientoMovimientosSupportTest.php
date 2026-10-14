<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\OrdencompraTratamientoMovimientosSupport;
use PHPUnit\Framework\TestCase;

class OrdencompraTratamientoMovimientosSupportTest extends TestCase
{
    public function test_permite_pasar_a_anticipada_con_anticipo_sin_com_y_una_recepcion(): void
    {
        $this->assertTrue(OrdencompraTratamientoMovimientosSupport::permitePasarAAnticipada(
            'NO ANTICIPADA',
            'ANTICIPADA',
            1,
            1,
        ));
    }

    public function test_permite_pasar_a_anticipada_sin_recepcion_si_el_anticipo_ya_esta_contabilizado(): void
    {
        $this->assertTrue(OrdencompraTratamientoMovimientosSupport::permitePasarAAnticipada(
            'NO ANTICIPADA',
            'ANTICIPADA',
            0,
            1,
        ));
    }

    public function test_no_permite_volver_a_no_anticipada(): void
    {
        $this->assertFalse(OrdencompraTratamientoMovimientosSupport::permitePasarAAnticipada(
            'ANTICIPADA',
            'NO ANTICIPADA',
            1,
            1,
        ));
    }

    public function test_no_permite_con_varias_recepciones(): void
    {
        $this->assertFalse(OrdencompraTratamientoMovimientosSupport::permitePasarAAnticipada(
            'NO ANTICIPADA',
            'ANTICIPADA',
            2,
            1,
        ));
    }

    public function test_no_permite_si_no_hay_anticipo_contabilizado_sin_com(): void
    {
        $this->assertFalse(OrdencompraTratamientoMovimientosSupport::permitePasarAAnticipada(
            'NO ANTICIPADA',
            'ANTICIPADA',
            1,
            0,
        ));
    }
}

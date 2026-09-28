<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ProyeccionPagosFechaSupport;
use PHPUnit\Framework\TestCase;

class ProyeccionPagosFechaSupportTest extends TestCase
{
    public function test_fecha_diferida_suma_dias_de_atraso_a_la_fecha_del_movimiento(): void
    {
        $this->assertSame(
            '2024-07-27',
            ProyeccionPagosFechaSupport::fechaDiferida('2024-06-27', 30)
        );
        $this->assertSame(
            '2024-07-25',
            ProyeccionPagosFechaSupport::fechaDiferida('2024-06-25 00:00:00', 30)
        );
    }

    public function test_sin_dias_de_atraso_queda_la_fecha_del_movimiento(): void
    {
        $this->assertSame(
            '2024-06-27',
            ProyeccionPagosFechaSupport::fechaDiferida('2024-06-27', 0)
        );
    }

    public function test_fecha_vacia_no_arma_diferida(): void
    {
        $this->assertNull(ProyeccionPagosFechaSupport::fechaDiferida(null, 30));
        $this->assertNull(ProyeccionPagosFechaSupport::fechaDiferida('', 30));
    }
}

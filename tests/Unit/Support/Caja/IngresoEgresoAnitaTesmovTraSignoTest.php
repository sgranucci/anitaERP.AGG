<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\IngresoEgresoAnitaTesmovSupport;
use Tests\TestCase;

/**
 * TRA 2306 (Kandiko, 10/09/2026) y su anulación TRA 2307.
 * La TEH de la anulación se grabó +13.000.000, igual que la original.
 */
class IngresoEgresoAnitaTesmovTraSignoTest extends TestCase
{
    public function test_alta_tra_2306_deja_teh_y_ted_positivos(): void
    {
        $salida = IngresoEgresoAnitaTesmovSupport::piernaTesmovTra(-13000000.0, 1.0);
        $entrada = IngresoEgresoAnitaTesmovSupport::piernaTesmovTra(13000000.0, 1.0);

        $this->assertSame('TEH', $salida['tipo']);
        $this->assertSame(1, $salida['sucursal']);
        $this->assertSame(13000000.0, $salida['importe']);

        $this->assertSame('TED', $entrada['tipo']);
        $this->assertSame(0, $entrada['sucursal']);
        $this->assertSame(13000000.0, $entrada['importe']);
    }

    public function test_anulacion_tra_2307_invierte_el_signo_de_teh_y_ted(): void
    {
        // El compensatorio ya invierte las líneas y grabarAnulacion aplica factor −1.
        $salida = IngresoEgresoAnitaTesmovSupport::piernaTesmovTra(13000000.0, -1.0);
        $entrada = IngresoEgresoAnitaTesmovSupport::piernaTesmovTra(-13000000.0, -1.0);

        $this->assertSame('TEH', $salida['tipo']);
        $this->assertSame(1, $salida['sucursal']);
        $this->assertSame(-13000000.0, $salida['importe']);

        $this->assertSame('TED', $entrada['tipo']);
        $this->assertSame(0, $entrada['sucursal']);
        $this->assertSame(-13000000.0, $entrada['importe']);
    }

    public function test_anulacion_no_repite_el_importe_positivo_del_original(): void
    {
        $original = IngresoEgresoAnitaTesmovSupport::piernaTesmovTra(-4500.55, 1.0);
        $anula = IngresoEgresoAnitaTesmovSupport::piernaTesmovTra(4500.55, -1.0);

        $this->assertSame($original['tipo'], $anula['tipo']);
        $this->assertSame('TEH', $anula['tipo']);
        $this->assertSame(-4500.55, $anula['importe']);
        $this->assertNotSame($original['importe'], $anula['importe']);
    }
}

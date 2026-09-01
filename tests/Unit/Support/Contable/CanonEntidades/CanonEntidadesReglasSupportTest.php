<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Contable\CanonEntidades;

use App\Support\Contable\CanonEntidades\CanonEntidadesReglasSupport;
use PHPUnit\Framework\TestCase;

class CanonEntidadesReglasSupportTest extends TestCase
{
    public function test_resuelve_biyemas_por_cuit_con_guiones(): void
    {
        $regla = CanonEntidadesReglasSupport::resolver('30-68240367-1');

        $this->assertSame('biyemas', $regla['regla']);
        $this->assertSame('BSA', $regla['codigo']);
        $this->assertTrue($regla['bingo_escalonado']);
        $this->assertTrue($regla['reconocida']);
    }

    public function test_resuelve_kandiko_y_rebisco_por_nombre(): void
    {
        $k = CanonEntidadesReglasSupport::resolver('', 'KANDIKO S.A.');
        $r = CanonEntidadesReglasSupport::resolver('', 'REBISCO S.A.');

        $this->assertSame('kandiko', $k['regla']);
        $this->assertSame('KSA', $k['codigo']);
        $this->assertFalse($k['bingo_escalonado']);
        $this->assertSame('rebisco', $r['regla']);
        $this->assertSame('RSA', $r['codigo']);
    }

    public function test_cuadra_con_tolerancia_de_un_peso(): void
    {
        $this->assertTrue(CanonEntidadesReglasSupport::cuadra(100.00, 100.00));
        $this->assertTrue(CanonEntidadesReglasSupport::cuadra(100.00, 99.05));
        $this->assertFalse(CanonEntidadesReglasSupport::cuadra(100.00, 98.90));
    }

    public function test_formatea_cuit(): void
    {
        $this->assertSame('30-68240367-1', CanonEntidadesReglasSupport::formatearCuit('30682403671'));
    }
}

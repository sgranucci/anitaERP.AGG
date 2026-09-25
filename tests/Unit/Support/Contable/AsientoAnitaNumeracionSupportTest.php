<?php

namespace Tests\Unit\Support\Contable;

use App\Support\Contable\AsientoAnitaNumeracionSupport;
use RuntimeException;
use Tests\TestCase;

class AsientoAnitaNumeracionSupportTest extends TestCase
{
    public function test_siguiente_libre_sin_ocupados(): void
    {
        $r = AsientoAnitaNumeracionSupport::siguienteLibre(100, static fn () => false);

        $this->assertSame(100, $r['numero']);
        $this->assertSame([], $r['saltados']);
    }

    public function test_siguiente_libre_salta_ocupados(): void
    {
        $ocupados = [230541 => true, 230542 => true];
        $r = AsientoAnitaNumeracionSupport::siguienteLibre(
            230541,
            static fn (int $n) => isset($ocupados[$n]),
        );

        $this->assertSame(230543, $r['numero']);
        $this->assertSame([230541, 230542], $r['saltados']);
    }

    public function test_siguiente_libre_agota_saltos(): void
    {
        $original = config('contable.asiento_numeracion_max_saltos_ocupados');
        config(['contable.asiento_numeracion_max_saltos_ocupados' => 2]);

        try {
            AsientoAnitaNumeracionSupport::siguienteLibre(10, static fn () => true);
            $this->fail('Debía agotar saltos');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('No se encontró número de asiento Anita libre', $e->getMessage());
        } finally {
            config(['contable.asiento_numeracion_max_saltos_ocupados' => $original]);
        }
    }

    public function test_candidato_invalido(): void
    {
        $this->expectException(RuntimeException::class);
        AsientoAnitaNumeracionSupport::siguienteLibre(0, static fn () => false);
    }

    public function test_where_ocupacion_solo_numero_en_bierzo(): void
    {
        $prev = config('app.empresa');
        config(['app.empresa' => 'EL BIERZO']);
        try {
            $this->assertTrue(AsientoAnitaNumeracionSupport::usaNumeradorVentasGlobal());
            $this->assertSame(
                ' WHERE ctav_nro_asiento = 2124679',
                AsientoAnitaNumeracionSupport::whereOcupacionCtamov(1, 2124679),
            );
            // Tras delete siempre empresa+nro
            $this->assertSame(
                " WHERE ctav_empresa = '1' AND ctav_nro_asiento = 2124679",
                AsientoAnitaNumeracionSupport::whereOcupacionCtamov(1, 2124679, true),
            );
        } finally {
            config(['app.empresa' => $prev]);
        }
    }

    public function test_where_ocupacion_empresa_y_numero_en_agg(): void
    {
        $prev = config('app.empresa');
        config(['app.empresa' => 'AGG']);
        try {
            $this->assertFalse(AsientoAnitaNumeracionSupport::usaNumeradorVentasGlobal());
            $this->assertSame(
                " WHERE ctav_empresa = '3' AND ctav_nro_asiento = 100",
                AsientoAnitaNumeracionSupport::whereOcupacionCtamov(3, 100),
            );
        } finally {
            config(['app.empresa' => $prev]);
        }
    }

    public function test_where_ocupacion_solo_numero_ferli_e_interforming(): void
    {
        $prev = config('app.empresa');
        try {
            config(['app.empresa' => 'CALZADOS FERLI']);
            $this->assertTrue(AsientoAnitaNumeracionSupport::usaNumeradorVentasGlobal());
            $this->assertStringStartsWith(' WHERE ctav_nro_asiento =', AsientoAnitaNumeracionSupport::whereOcupacionCtamov(1, 50));

            config(['app.empresa' => 'INTERFORMING']);
            $this->assertTrue(AsientoAnitaNumeracionSupport::usaNumeradorVentasGlobal());
            $this->assertSame(
                ' WHERE ctav_nro_asiento = 50',
                AsientoAnitaNumeracionSupport::whereOcupacionCtamov(1, 50),
            );
        } finally {
            config(['app.empresa' => $prev]);
        }
    }
}

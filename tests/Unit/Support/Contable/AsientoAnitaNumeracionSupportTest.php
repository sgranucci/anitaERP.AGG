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
}

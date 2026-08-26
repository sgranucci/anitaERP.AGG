<?php

namespace Tests\Unit\Support\Contable;

use App\Support\Contable\AsientoAnitaNumeracionLock;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class AsientoAnitaNumeracionLockTest extends TestCase
{
    public function test_clave_por_empresa(): void
    {
        $this->assertSame(
            'contable:asiento-numeracion-anita:3',
            AsientoAnitaNumeracionLock::clave(3),
        );
    }

    public function test_segunda_reserva_espera_y_falla_si_no_libera(): void
    {
        $lock = Cache::lock(AsientoAnitaNumeracionLock::clave(3), 15);
        $this->assertTrue($lock->get());

        $esperaOriginal = config('contable.asiento_numeracion_lock_espera_segundos');
        config(['contable.asiento_numeracion_lock_espera_segundos' => 1]);

        try {
            AsientoAnitaNumeracionLock::conExclusividad(3, static fn () => true);
            $this->fail('Debía fallar si el numerador ya está tomado.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('número de asiento', $e->getMessage());
        } finally {
            config(['contable.asiento_numeracion_lock_espera_segundos' => $esperaOriginal]);
            $lock->release();
        }
    }

    public function test_empresa_invalida_no_bloquea(): void
    {
        $this->assertSame('ok', AsientoAnitaNumeracionLock::conExclusividad(0, static fn () => 'ok'));
    }
}

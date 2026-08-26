<?php

namespace Tests\Unit\Support\Contable;

use App\Support\Contable\CierreRendicionEstacionamientoCierreLock;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

class CierreRendicionEstacionamientoCierreLockTest extends TestCase
{
    public function test_clave_por_empresa(): void
    {
        $this->assertSame(
            'contable:cierre-estacionamiento:empresa:3',
            CierreRendicionEstacionamientoCierreLock::claveEmpresa(3),
        );
    }

    public function test_segunda_adquisicion_sin_espera_falla(): void
    {
        $lock = Cache::lock(CierreRendicionEstacionamientoCierreLock::claveEmpresa(3), 15);
        $this->assertTrue($lock->get());

        try {
            CierreRendicionEstacionamientoCierreLock::conExclusividadEmpresa(3, static fn () => true, false);
            $this->fail('Debía rechazar un segundo cierre en paralelo.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('en curso', $e->getMessage());
        } finally {
            $lock->release();
        }
    }

    public function test_callback_corre_con_el_lock(): void
    {
        $valor = CierreRendicionEstacionamientoCierreLock::conExclusividadEmpresa(
            3,
            static fn () => 42,
            false,
        );

        $this->assertSame(42, $valor);
    }
}

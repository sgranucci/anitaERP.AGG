<?php

namespace Tests\Unit\Support\Contable;

use App\Support\Contable\CierreRendicionBingoCierreLock;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

class CierreRendicionBingoCierreLockTest extends TestCase
{
    public function test_clave_por_empresa(): void
    {
        $this->assertSame(
            'contable:cierre-bingo:empresa:2',
            CierreRendicionBingoCierreLock::claveEmpresa(2),
        );
    }

    public function test_segunda_adquisicion_sin_espera_falla(): void
    {
        $lock = Cache::lock(CierreRendicionBingoCierreLock::claveEmpresa(2), 15);
        $this->assertTrue($lock->get());

        try {
            CierreRendicionBingoCierreLock::conExclusividadEmpresa(2, static fn () => true, false);
            $this->fail('Debía rechazar un segundo cierre en paralelo.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('en curso', $e->getMessage());
        } finally {
            $lock->release();
        }
    }

    public function test_callback_corre_con_el_lock(): void
    {
        $valor = CierreRendicionBingoCierreLock::conExclusividadEmpresa(
            2,
            static fn () => 42,
            false,
        );

        $this->assertSame(42, $valor);
    }
}

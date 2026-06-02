<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\AnitaSync\RendicionGastronomiaAnitaIdempotenciaSupport;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class RendicionGastronomiaAnitaIdempotenciaSupportTest extends TestCase
{
    public function test_elige_nro_oper_erp_si_coincide_con_anita(): void
    {
        $canonico = $this->elegirCanonico([3, 4, 5], 4);

        $this->assertSame(4, $canonico);
    }

    public function test_elige_menor_en_anita_si_erp_no_coincide(): void
    {
        $canonico = $this->elegirCanonico([8, 3, 5], 99);

        $this->assertSame(3, $canonico);
    }

    public function test_usa_erp_si_anita_vacio(): void
    {
        $canonico = $this->elegirCanonico([], 12);

        $this->assertSame(12, $canonico);
    }

    /**
     * @param  list<int>  $enAnita
     */
    private function elegirCanonico(array $enAnita, int $desdeErp): int
    {
        $metodo = new ReflectionMethod(
            RendicionGastronomiaAnitaIdempotenciaSupport::class,
            'elegirNroOperCanonico',
        );
        $metodo->setAccessible(true);

        return $metodo->invoke(null, $enAnita, $desdeErp);
    }
}

<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\CierreJornadaProcesoPuntoventaSupport;
use InvalidArgumentException;
use Tests\TestCase;

final class CierreJornadaProcesoPuntoventaSupportTest extends TestCase
{
    protected function tearDown(): void
    {
        config(['gastronomia.cierre_jornada_puntoventa_codigo_por_empresa' => []]);
        parent::tearDown();
    }

    public function test_codigo_configurado_normaliza_ceros_a_la_izquierda(): void
    {
        config(['gastronomia.cierre_jornada_puntoventa_codigo_por_empresa' => [1 => '00003']]);

        $this->assertSame('00003', CierreJornadaProcesoPuntoventaSupport::codigoConfigurado(1));
        $this->assertSame('00003', CierreJornadaProcesoPuntoventaSupport::codigoConfigurado(1));
    }

    public function test_codigo_configurado_acepta_clave_string(): void
    {
        config(['gastronomia.cierre_jornada_puntoventa_codigo_por_empresa' => ['1' => '3']]);

        $this->assertSame('00003', CierreJornadaProcesoPuntoventaSupport::codigoConfigurado(1));
    }

    public function test_resolver_o_error_lanza_si_no_hay_config(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No está configurado el punto de venta');

        CierreJornadaProcesoPuntoventaSupport::resolverOError(99999);
    }
}

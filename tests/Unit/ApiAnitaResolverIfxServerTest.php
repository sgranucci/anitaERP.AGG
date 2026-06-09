<?php

namespace Tests\Unit;

use App\ApiAnita;
use ReflectionMethod;
use Tests\TestCase;

final class ApiAnitaResolverIfxServerTest extends TestCase
{
    private static function resolverIfxServer(?string $clave = null): string
    {
        $method = new ReflectionMethod(ApiAnita::class, 'resolverIfxServer');

        return (string) $method->invoke(null, $clave);
    }

    public function test_resuelve_claves_env(): void
    {
        config([
            'anita.ifx_servers' => [
                'IFX_SERVER' => 'bi7ncadmin',
                'IFX_SERVER_LOCAL' => 'bincadmin',
            ],
            'anita.ifx_server' => 'bi7ncadmin',
        ]);

        $this->assertSame('bincadmin', self::resolverIfxServer('IFX_SERVER_LOCAL'));
    }

    public function test_acepta_nombre_informix_literal(): void
    {
        config([
            'anita.ifx_servers' => [
                'IFX_SERVER' => 'bi7ncadmin',
            ],
            'anita.ifx_server' => 'bi7ncadmin',
        ]);

        $this->assertSame('kancadmin', self::resolverIfxServer('kancadmin'));
        $this->assertSame('rencadmin', self::resolverIfxServer('rencadmin'));
    }

    public function test_sin_clave_usa_global(): void
    {
        config([
            'anita.ifx_servers' => [],
            'anita.ifx_server' => 'bi7ncadmin',
        ]);

        $this->assertSame('bi7ncadmin', self::resolverIfxServer());
    }
}

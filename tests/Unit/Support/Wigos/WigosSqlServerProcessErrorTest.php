<?php

namespace Tests\Unit\Support\Wigos;

use App\Support\Wigos\WigosSqlServerProcess;
use RuntimeException;
use Tests\TestCase;

class WigosSqlServerProcessErrorTest extends TestCase
{
    public function test_detecta_errores_de_conexion_y_espejo(): void
    {
        self::assertTrue(WigosSqlServerProcess::esErrorConexionOEspejo(
            new RuntimeException('Wigos A: Login timeout expired')
        ));
        self::assertTrue(WigosSqlServerProcess::esErrorConexionOEspejo(
            new RuntimeException('Wigos A: database is restoring')
        ));
        self::assertTrue(WigosSqlServerProcess::esErrorConexionOEspejo(
            new RuntimeException('Wigos A: no responde (login timeout) — verificar red')
        ));
        self::assertFalse(WigosSqlServerProcess::esErrorConexionOEspejo(
            new RuntimeException('Wigos A: timeout de ejecución del subproceso SQL (180s).')
        ));
        self::assertFalse(WigosSqlServerProcess::esErrorConexionOEspejo(
            new RuntimeException('Wigos A: subproceso SQL sin éxito')
        ));
    }
}

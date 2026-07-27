<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\ApiAnita;
use PHPUnit\Framework\TestCase;

final class ApiAnitaParsearListaTest extends TestCase
{
    public function test_lista_vacia_sin_error(): void
    {
        $parsed = ApiAnita::parsearRespuestaLista('[]');

        $this->assertSame([], $parsed['filas']);
        $this->assertNull($parsed['error_lectura']);
    }

    public function test_objeto_error_no_se_trata_como_lista_vacia(): void
    {
        $parsed = ApiAnita::parsearRespuestaLista('{"Error":"timeout bridge"}');

        $this->assertSame([], $parsed['filas']);
        $this->assertSame('timeout bridge', $parsed['error_lectura']);
    }

    public function test_fila_unica_como_objeto(): void
    {
        $parsed = ApiAnita::parsearRespuestaLista('{"ven_tipo":"FAC","ven_nro":123}');

        $this->assertCount(1, $parsed['filas']);
        $this->assertSame('FAC', $parsed['filas'][0]->ven_tipo);
        $this->assertNull($parsed['error_lectura']);
    }

    public function test_sin_respuesta_es_error_lectura(): void
    {
        $parsed = ApiAnita::parsearRespuestaLista(null);

        $this->assertSame([], $parsed['filas']);
        $this->assertNotNull($parsed['error_lectura']);
    }

    public function test_extraer_filas_afectadas_y_exito_escritura(): void
    {
        $this->assertSame(1, ApiAnita::extraerFilasAfectadas("1 row(s) updated.\n"));
        $this->assertSame(0, ApiAnita::extraerFilasAfectadas('0 row(s) updated.'));
        $this->assertNull(ApiAnita::extraerFilasAfectadas('[]'));
        $this->assertNull(ApiAnita::extraerFilasAfectadas(''));

        $this->assertTrue(ApiAnita::respuestaBridgeEscrituraExitosa('1 row(s) updated.'));
        $this->assertFalse(ApiAnita::respuestaBridgeEscrituraExitosa('0 row(s) updated.'));
        $this->assertFalse(ApiAnita::respuestaBridgeEscrituraExitosa('[]'));
    }
}

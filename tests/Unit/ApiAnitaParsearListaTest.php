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

    public function test_unload_en_escritura_es_error(): void
    {
        $msg = ApiAnita::mensajeRespuestaUnloadEnEscritura('2 row(s) unloaded.');
        $this->assertNotNull($msg);
        $this->assertStringContainsString('unload', strtolower((string) $msg));

        $this->assertSame(
            null,
            ApiAnita::extraerMensajeError("2 row(s) unloaded.\n")
        );
        $this->assertNotNull(ApiAnita::mensajeRespuestaUnloadEnEscritura('2 row(s) unloaded.'));

        // Insert confirmado no se trata como unload.
        $this->assertNull(ApiAnita::mensajeRespuestaUnloadEnEscritura('1 row(s) inserted.'));
        $this->assertNull(ApiAnita::extraerMensajeError('1 row(s) inserted.'));
    }

    public function test_warning_fopen_csv_es_error_lectura_reintentable(): void
    {
        $html = '<br />'."\n"
            .'<b>Warning</b>:  fopen(/usr2/biyemas/shared/cmd_sql.17897565-list-25081.csv) '
            .'[function.fopen]: failed to open stream: No such file or directory in '
            .'/usr2/www/htdocs/apiERP.php on line 62<br />'."\n"
            .'<b>Warning</b>:  fgets(): supplied argument is not a valid stream resource in '
            .'/usr2/www/htdocs/apiERP.php on line 64<br />'."\n"
            .'[]';

        $parsed = ApiAnita::parsearRespuestaLista($html);
        $this->assertSame([], $parsed['filas']);
        $this->assertNotNull($parsed['error_lectura']);
        $this->assertTrue(ApiAnita::esErrorCsvUnloadFaltante($parsed['error_lectura']));

        $this->assertTrue(ApiAnita::esErrorCsvUnloadFaltante(
            'UNLOAD no generó el archivo CSV (revisar permisos, ruta o SQL Informix).'
        ));
        $this->assertFalse(ApiAnita::esErrorCsvUnloadFaltante('timeout bridge'));
        $this->assertFalse(ApiAnita::esErrorCsvUnloadFaltante(null));
        $this->assertFalse(ApiAnita::esErrorCsvUnloadFaltante('[]'));
    }
}

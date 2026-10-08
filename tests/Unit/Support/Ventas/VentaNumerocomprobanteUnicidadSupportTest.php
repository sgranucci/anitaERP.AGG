<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\VentaNumerocomprobanteUnicidadSupport;
use Illuminate\Database\QueryException;
use PDOException;
use Tests\TestCase;

final class VentaNumerocomprobanteUnicidadSupportTest extends TestCase
{
    public function test_detecta_violacion_por_nombre_indice(): void
    {
        $e = new QueryException(
            'mysql',
            '23000',
            new PDOException(
                "Duplicate entry '24-184405' for key '".VentaNumerocomprobanteUnicidadSupport::UNIQUE_INDEX."'",
                1062,
            ),
            [],
        );

        $this->assertTrue(VentaNumerocomprobanteUnicidadSupport::esViolacionNumerocomprobante($e));
    }

    public function test_ignora_otras_violaciones_unique(): void
    {
        $e = new QueryException(
            'mysql',
            '23000',
            new PDOException("Duplicate entry '1-2' for key 'cobranza.empresa_tipo_numero_unique'", 1062),
            [],
        );

        $this->assertFalse(VentaNumerocomprobanteUnicidadSupport::esViolacionNumerocomprobante($e));
    }

    public function test_detecta_violacion_postgres_por_nombre_indice(): void
    {
        $e = new \RuntimeException(
            'ERROR: duplicate key value violates unique constraint "'
            .VentaNumerocomprobanteUnicidadSupport::UNIQUE_INDEX.'"'
        );

        $this->assertTrue(VentaNumerocomprobanteUnicidadSupport::esViolacionNumerocomprobante($e));
    }

    public function test_reconoce_espejo_anita_de_consulta(): void
    {
        $this->assertTrue(VentaNumerocomprobanteUnicidadSupport::esPayloadVinculoConsulta([
            'origen' => 'anita_local',
            'vinculo_consulta' => true,
        ]));
        $this->assertFalse(VentaNumerocomprobanteUnicidadSupport::esPayloadVinculoConsulta(['origen' => 'anita_local']));
        $this->assertFalse(VentaNumerocomprobanteUnicidadSupport::esPayloadVinculoConsulta(null));
    }

    public function test_marca_codigo_de_espejo_anita_sin_pisar_el_fiscal(): void
    {
        $marcado = VentaNumerocomprobanteUnicidadSupport::codigoMarcadoEspejoAnita('NCD B-00017-00002463');

        $this->assertSame('NCD B-00017-00002463 · Anita sin CAE', $marcado);
        $this->assertSame(
            $marcado,
            VentaNumerocomprobanteUnicidadSupport::codigoMarcadoEspejoAnita($marcado),
        );
        $this->assertLessThanOrEqual(100, mb_strlen($marcado));
    }

    public function test_el_espejo_que_cedio_el_numero_no_es_comprobante_fiscal(): void
    {
        $this->assertTrue(VentaNumerocomprobanteUnicidadSupport::esEspejoAnitaCedido(
            'NCD B-00017-00002463 · Anita sin CAE',
        ));
        $this->assertFalse(VentaNumerocomprobanteUnicidadSupport::esEspejoAnitaCedido(
            'NCD B-00017-00002463',
        ));
    }
}

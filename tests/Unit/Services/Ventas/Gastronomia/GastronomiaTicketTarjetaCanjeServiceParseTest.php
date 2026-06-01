<?php

namespace Tests\Unit\Services\Ventas\Gastronomia;

use App\Services\Ventas\Gastronomia\GastronomiaTicketTarjetaCanjeService;
use InvalidArgumentException;
use Tests\TestCase;

class GastronomiaTicketTarjetaCanjeServiceParseTest extends TestCase
{
    private GastronomiaTicketTarjetaCanjeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GastronomiaTicketTarjetaCanjeService::class);
    }

    public function test_parse_codigo_barras_ean_con_relleno_y_verificador(): void
    {
        $this->assertSame([554101, 52547], $this->service->parseCodigoBarras('5541010525473'));
        $this->assertSame([554101, 52547], $this->service->parseCodigoBarras('554101-0525473'));
    }

    public function test_parse_codigo_barras_impreso_con_guion(): void
    {
        $this->assertSame([554101, 52547], $this->service->parseCodigoBarras('554101-52547'));
    }

    public function test_parse_codigo_barras_sin_guion_ejemplo_env(): void
    {
        $this->assertSame([553659, 52217], $this->service->parseCodigoBarras('55365952217'));
    }

    public function test_candidatos_incluyen_parseo_naive_como_respaldo(): void
    {
        $candidatos = $this->service->candidatosParseoCodigo('5541010525473');
        $this->assertSame([554101, 52547], $candidatos[0]);
        $this->assertContains([554101, 525473], $candidatos);
    }

    public function test_rechaza_codigo_vacio(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->parseCodigoBarras('');
    }

    public function test_extraer_movimiento_desde_formato_ocr(): void
    {
        $ref = new \ReflectionMethod(GastronomiaTicketTarjetaCanjeService::class, 'extraerMovimientoId');
        $ref->setAccessible(true);

        $this->assertSame(554101, $ref->invoke($this->service, '554101-0525473'));
        $this->assertSame(554101, $ref->invoke($this->service, '5541010525473'));
    }

    public function test_similitud_inroticket_tolera_cero_y_digito_verificador(): void
    {
        $ref = new \ReflectionMethod(GastronomiaTicketTarjetaCanjeService::class, 'puntajeSimilitudInroticket');
        $ref->setAccessible(true);

        $this->assertGreaterThanOrEqual(120, $ref->invoke($this->service, 525473, 52547));
        $this->assertGreaterThanOrEqual(120, $ref->invoke($this->service, 52547, 525473));
        $this->assertSame(1000, $ref->invoke($this->service, 52547, 52547));
    }
}

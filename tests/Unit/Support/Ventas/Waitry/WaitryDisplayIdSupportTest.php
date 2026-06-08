<?php

namespace Tests\Unit\Support\Ventas\Waitry;

use App\Support\Ventas\Waitry\WaitryDisplayIdSupport;
use PHPUnit\Framework\TestCase;

final class WaitryDisplayIdSupportTest extends TestCase
{
    public function test_extrae_display_id_desde_orden(): void
    {
        $this->assertSame('A-42', WaitryDisplayIdSupport::extraerDesdeOrden([
            'display_id' => 'A-42',
            'orderId' => 999,
        ]));
    }

    public function test_extrae_external_delivery_id_alfanumerico_desde_respuesta_push(): void
    {
        $this->assertSame('E-26BADA', WaitryDisplayIdSupport::extraerDesdeRespuestaPush([
            'ok' => true,
            'msg' => 'pushExternalOrder: finish',
            'response' => [
                'orderId' => 17580915,
                'external_id' => '2804',
                'external_delivery_id' => 'E-26BADA',
            ],
        ]));
    }

    public function test_extrae_external_delivery_id_numerico_desde_respuesta_push(): void
    {
        $this->assertSame('301', WaitryDisplayIdSupport::extraerDesdeRespuestaPush([
            'ok' => true,
            'msg' => 'pushExternalOrder: finish',
            'response' => [
                'orderId' => 17613458,
                'external_id' => '4855',
                'external_delivery_id' => 301,
            ],
        ]));
    }

    public function test_prioriza_external_delivery_id_numerico_sobre_display_id(): void
    {
        $this->assertSame('301', WaitryDisplayIdSupport::extraerDesdeOrden([
            'external_delivery_id' => 301,
            'display_id' => 'E-35E198',
        ]));
    }

    public function test_extrae_sequence_desde_orden_getordersdetails(): void
    {
        $this->assertSame('313', WaitryDisplayIdSupport::extraerDesdeOrden([
            'orderId' => 17613784,
            'sequence' => '313',
            'externalDeliveryId' => 'E-9397A4',
        ]));
    }

    public function test_order_id_desde_ordenes_por_secuencia(): void
    {
        $ordenes = [
            ['orderId' => 17613458, 'sequence' => '301'],
            ['orderId' => 17613784, 'sequence' => '313'],
        ];

        $this->assertSame(17613458, WaitryDisplayIdSupport::orderIdDesdeOrdenesPorSecuencia('301', $ordenes));
        $this->assertSame(17613784, WaitryDisplayIdSupport::orderIdDesdeOrdenesPorSecuencia('313', $ordenes));
        $this->assertNull(WaitryDisplayIdSupport::orderIdDesdeOrdenesPorSecuencia('999', $ordenes));
    }

    public function test_extrae_desde_respuesta_push_anidada(): void
    {
        $this->assertSame('K7X', WaitryDisplayIdSupport::extraerDesdeRespuestaPush([
            'ok' => true,
            'response' => [
                'orderId' => 123,
                'externalId' => '456',
                'display_id' => 'K7X',
            ],
        ]));
    }

    public function test_respuesta_push_sin_identificador_retorna_vacio(): void
    {
        $this->assertSame('', WaitryDisplayIdSupport::extraerDesdeRespuestaPush([
            'ok' => true,
            'response' => [
                'orderId' => 123,
                'externalId' => '456',
            ],
        ]));
    }

    public function test_ignora_order_id_global_como_contador_monitor(): void
    {
        $this->assertFalse(WaitryDisplayIdSupport::esContadorMonitorNumerico(17613458));
        $this->assertSame('', WaitryDisplayIdSupport::normalizarContadorMonitor(17613458));
    }

    public function test_es_identificador_monitor_valido_acepta_numerico_y_alfanumerico(): void
    {
        $this->assertTrue(WaitryDisplayIdSupport::esIdentificadorMonitorValido('301'));
        $this->assertTrue(WaitryDisplayIdSupport::esIdentificadorMonitorValido('E-C7F040'));
        $this->assertFalse(WaitryDisplayIdSupport::esIdentificadorMonitorValido('17613458'));
        $this->assertFalse(WaitryDisplayIdSupport::esIdentificadorMonitorValido(''));
    }

    public function test_es_codigo_monitor_alfanumerico_rechaza_solo_numeros(): void
    {
        $this->assertFalse(WaitryDisplayIdSupport::esCodigoMonitorAlfanumerico('331'));
        $this->assertTrue(WaitryDisplayIdSupport::esCodigoMonitorAlfanumerico('E-C7F040'));
    }
}

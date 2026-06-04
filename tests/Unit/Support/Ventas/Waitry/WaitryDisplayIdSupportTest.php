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

    public function test_respuesta_push_sin_display_id_retorna_vacio(): void
    {
        $this->assertSame('', WaitryDisplayIdSupport::extraerDesdeRespuestaPush([
            'ok' => true,
            'response' => [
                'orderId' => 123,
                'externalId' => '456',
            ],
        ]));
    }
}

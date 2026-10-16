<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\ArticuloWaitryEstadoSupport;
use App\Support\Ventas\Waitry\WaitryArticuloMenuPayloadSupport;
use PHPUnit\Framework\TestCase;

final class ArticuloWaitryEstadoSupportTest extends TestCase
{
    public function test_sin_envios_permite_alta_y_no_baja(): void
    {
        $r = ArticuloWaitryEstadoSupport::decidir([]);

        $this->assertNull($r['vigente']);
        $this->assertTrue($r['puede_alta']);
        $this->assertFalse($r['puede_baja']);
        $this->assertSame('Sin envío', $r['estado']);
    }

    public function test_alta_ok_se_puede_volver_a_enviar_y_permite_baja(): void
    {
        $r = ArticuloWaitryEstadoSupport::decidir([
            ['operacion' => 'alta', 'ok' => false, 'mensaje' => 'timeout'],
            ['operacion' => 'alta', 'ok' => true, 'mensaje' => 'Alta aceptada.'],
        ]);

        $this->assertSame('alta', $r['vigente']);
        $this->assertTrue($r['puede_alta']);
        $this->assertTrue($r['puede_baja']);
        $this->assertStringContainsString('actualiza', $r['estado']);
    }

    public function test_baja_fallida_deja_reintentar_la_baja_y_el_alta(): void
    {
        $r = ArticuloWaitryEstadoSupport::decidir([
            ['operacion' => 'alta', 'ok' => true],
            ['operacion' => 'baja', 'ok' => false, 'mensaje' => 'rechazado'],
        ]);

        $this->assertSame('alta', $r['vigente']);
        $this->assertTrue($r['puede_alta']);
        $this->assertTrue($r['puede_baja']);
        $this->assertStringContainsString('rechazado', $r['estado']);
    }

    public function test_baja_ok_permite_alta_y_no_otra_baja(): void
    {
        $r = ArticuloWaitryEstadoSupport::decidir([
            ['operacion' => 'alta', 'ok' => true],
            ['operacion' => 'baja', 'ok' => true],
        ]);

        $this->assertSame('baja', $r['vigente']);
        $this->assertTrue($r['puede_alta']);
        $this->assertFalse($r['puede_baja']);
        $this->assertStringContainsString('2040', $r['estado']);
    }

    public function test_alta_preparar_manda_menu_y_categorias_vacios(): void
    {
        $payload = WaitryArticuloMenuPayloadSupport::alta('v0647', 'Agua', true);

        $this->assertSame([], $payload['Menu']);
        $this->assertSame([], $payload['Categories']);
        $this->assertSame('V0647', $payload['Items'][0]['externalId']);
        $this->assertSame('V0647', $payload['Items'][0]['id']);
        $this->assertSame('Agua', $payload['Items'][0]['name']);
    }

    public function test_alta_no_preparar_manda_la_categoria(): void
    {
        $payload = WaitryArticuloMenuPayloadSupport::alta('V0647', 'Agua', false);

        $this->assertSame('NO-PREPARAR', $payload['Categories'][0]['externalId']);
        $this->assertSame('V0647', $payload['Categories'][0]['entities'][0]['id']);
    }

    public function test_baja_suspende_hasta_2040(): void
    {
        $payload = WaitryArticuloMenuPayloadSupport::baja('V0001');

        $this->assertSame('V0001', $payload['itemId']);
        $this->assertSame(WaitryArticuloMenuPayloadSupport::SUSPENDER_HASTA, $payload['suspension_info']['suspend_until']);
    }

    public function test_respuesta_ok_false_no_cuenta_como_alta(): void
    {
        $r = WaitryArticuloMenuPayloadSupport::interpretar([
            'ok' => false,
            'msg' => 'item duplicado',
        ]);

        $this->assertFalse($r['ok']);
        $this->assertSame('item duplicado', $r['mensaje']);
    }
}

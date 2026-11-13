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

    public function test_alta_preparar_manda_menus_y_categorias_vacios(): void
    {
        $payload = WaitryArticuloMenuPayloadSupport::alta('v0647', 'Agua', true);

        $this->assertSame([], $payload['menus']);
        $this->assertSame([], $payload['categories']);
        $this->assertSame('V0647', $payload['items'][0]['id']);
        $this->assertSame('Agua', $payload['items'][0]['title']);
        $this->assertSame(0, $payload['items'][0]['price_info']['price']);
    }

    public function test_alta_no_preparar_manda_menu_categoria_e_item(): void
    {
        $payload = WaitryArticuloMenuPayloadSupport::alta('V0950', 'CIGARRILLOS', false);

        $this->assertSame('NO-PREPARAR', $payload['menus'][0]['id']);
        $this->assertSame(['NO-PREPARAR'], $payload['menus'][0]['category_ids']);
        $this->assertSame('NO-PREPARAR', $payload['categories'][0]['id']);
        $this->assertSame('ITEM', $payload['categories'][0]['entities'][0]['type']);
        $this->assertSame('V0950', $payload['categories'][0]['entities'][0]['id']);
        $this->assertSame('V0950', $payload['items'][0]['id']);
        $this->assertSame('CIGARRILLOS', $payload['items'][0]['title']);
        $this->assertSame(0, $payload['items'][0]['price_info']['price']);
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

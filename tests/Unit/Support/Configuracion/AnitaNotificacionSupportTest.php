<?php

namespace Tests\Unit\Support\Configuracion;

use App\Services\Configuracion\AnitaNotificacionService;
use App\Support\Configuracion\AnitaNotificacionRetencionSupport;
use App\Support\Configuracion\AnitaNotificacionWebhookSupport;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Tests puros (sin BD). La lógica de persistencia vive en AnitaNotificacionService
 * y se valida en el servidor de test (sqlite).
 */
class AnitaNotificacionSupportTest extends TestCase
{
    public function test_master_switch_deshabilita_servicio(): void
    {
        config([
            'anita_notificacion.habilitado' => false,
            'anita_notificacion.productores_extra' => true,
        ]);
        $svc = app(AnitaNotificacionService::class);

        $this->assertFalse($svc->estaHabilitado());
        $this->assertFalse($svc->productoresExtraHabilitados());
        $this->assertNull($svc->crear(1, 'No debe persistir'));
        $this->assertNull($svc->avisarSistema(1, 'Tampoco'));
        $this->assertSame(0, $svc->contarNoLeidas(1));
    }

    public function test_productores_extra_requiere_master(): void
    {
        config([
            'anita_notificacion.habilitado' => true,
            'anita_notificacion.productores_extra' => false,
        ]);
        $svc = app(AnitaNotificacionService::class);

        $this->assertTrue($svc->estaHabilitado());
        $this->assertFalse($svc->productoresExtraHabilitados());
        $this->assertNull($svc->avisarSistema(2, 'Ticket'));
        $this->assertSame([], $svc->avisarSistemaAUsuarios([1, 2], 'Grupo'));
    }

    public function test_webhook_apagado_devuelve_null(): void
    {
        $this->assertNull(AnitaNotificacionWebhookSupport::resolverParaUsuario(
            1,
            ['habilitado' => false, 'slack_url' => 'https://x', 'teams_url' => ''],
            ['habilitado' => false]
        ));
    }

    public function test_webhook_legacy_arbol_como_fallback(): void
    {
        $r = AnitaNotificacionWebhookSupport::resolverParaUsuario(
            1,
            ['habilitado' => false, 'slack_url' => '', 'teams_url' => ''],
            ['habilitado' => true, 'slack_url' => 'https://hooks.slack/legacy', 'teams_url' => '']
        );

        $this->assertSame('https://hooks.slack/legacy', $r['slack_url']);
    }

    public function test_webhook_por_usuario_gana_sobre_global(): void
    {
        $r = AnitaNotificacionWebhookSupport::resolverParaUsuario(
            11,
            [
                'habilitado' => true,
                'slack_url' => 'https://hooks.slack/global',
                'teams_url' => 'https://teams/global',
            ],
            [],
            [11 => ['slack_url' => 'https://hooks.slack/user-11', 'teams_url' => '']]
        );

        $this->assertSame('https://hooks.slack/user-11', $r['slack_url']);
        $this->assertSame('', $r['teams_url']);
    }

    public function test_mapa_json_fusiona_con_config(): void
    {
        $mapa = AnitaNotificacionWebhookSupport::mapaDesdeConfigYJson(
            [5 => ['slack_url' => 'https://a']],
            '{"7":{"teams_url":"https://b"}}'
        );

        $this->assertSame('https://a', $mapa[5]['slack_url']);
        $this->assertSame('https://b', $mapa[7]['teams_url']);
    }

    public function test_mapa_json_invalido_conserva_config(): void
    {
        $mapa = AnitaNotificacionWebhookSupport::mapaDesdeConfigYJson(
            [1 => ['slack_url' => 'https://ok']],
            '{no-json'
        );

        $this->assertSame(['slack_url' => 'https://ok'], $mapa[1]);
    }

    public function test_payloads_incluyen_link(): void
    {
        $slack = AnitaNotificacionWebhookSupport::payloadSlack('Título', 'Cuerpo', 'https://app/x');
        $this->assertStringContainsString('Título — Cuerpo', $slack['text']);
        $this->assertStringContainsString('<https://app/x|Abrir>', $slack['text']);

        $teams = AnitaNotificacionWebhookSupport::payloadTeams('Título', null, 'https://app/y');
        $this->assertSame('MessageCard', $teams['@type']);
        $this->assertStringContainsString('[Abrir](https://app/y)', $teams['text']);
    }

    public function test_retencion_cortes(): void
    {
        $ahora = Carbon::parse('2026-09-06 12:00:00');
        $c = AnitaNotificacionRetencionSupport::cortes(90, 180, $ahora);

        $this->assertSame('2026-06-08 12:00:00', $c['corte_leidas']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-10 12:00:00', $c['corte_no_leidas']->format('Y-m-d H:i:s'));

        $sinNoLeidas = AnitaNotificacionRetencionSupport::cortes(30, 0, $ahora);
        $this->assertNotNull($sinNoLeidas['corte_leidas']);
        $this->assertNull($sinNoLeidas['corte_no_leidas']);
    }
}

<?php

namespace Tests\Unit\Support\Wigos;

use App\Support\Wigos\WigosActiveServerStore;
use App\Support\Wigos\WigosConfigResolver;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WigosActiveServerStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('wigos.monitor_servidor_activo.habilitado', true);
        config()->set('wigos.monitor_servidor_activo.ok_para_preferido', 2);
        WigosActiveServerStore::reset(0);
        WigosActiveServerStore::reset(2);
    }

    protected function tearDown(): void
    {
        WigosActiveServerStore::reset(0);
        WigosActiveServerStore::reset(2);
        parent::tearDown();
    }

    public function test_publica_secundario_cuando_preferido_falla(): void
    {
        self::assertNull(WigosActiveServerStore::aliasActivo(0));

        WigosActiveServerStore::registrarChequeos(0, [
            'A' => ['ok' => false, 'error' => 'is restoring', 'host' => 'a'],
            'B' => ['ok' => true, 'error' => null, 'host' => 'b'],
        ], 'A');

        self::assertSame('B', WigosActiveServerStore::aliasActivo(0));
    }

    public function test_vuelve_a_preferido_tras_ok_consecutivos(): void
    {
        config()->set('wigos.monitor_servidor_activo.ok_para_preferido', 2);

        WigosActiveServerStore::registrarChequeos(0, [
            'A' => ['ok' => false, 'error' => 'restoring', 'host' => 'a'],
            'B' => ['ok' => true, 'error' => null, 'host' => 'b'],
        ], 'A');
        self::assertSame('B', WigosActiveServerStore::aliasActivo(0));

        // Ambos OK pero preferido recién recuperado: se queda en B
        WigosActiveServerStore::registrarChequeos(0, [
            'A' => ['ok' => true, 'error' => null, 'host' => 'a'],
            'B' => ['ok' => true, 'error' => null, 'host' => 'b'],
        ], 'A');
        self::assertSame('B', WigosActiveServerStore::aliasActivo(0));

        // Segundo OK consecutivo en preferido → vuelve a A
        WigosActiveServerStore::registrarChequeos(0, [
            'A' => ['ok' => true, 'error' => null, 'host' => 'a'],
            'B' => ['ok' => true, 'error' => null, 'host' => 'b'],
        ], 'A');
        self::assertSame('A', WigosActiveServerStore::aliasActivo(0));
    }

    public function test_conserva_activo_si_ambos_fallan(): void
    {
        WigosActiveServerStore::registrarChequeos(0, [
            'A' => ['ok' => false, 'error' => 'down', 'host' => 'a'],
            'B' => ['ok' => true, 'error' => null, 'host' => 'b'],
        ], 'A');
        self::assertSame('B', WigosActiveServerStore::aliasActivo(0));

        WigosActiveServerStore::registrarChequeos(0, [
            'A' => ['ok' => false, 'error' => 'down', 'host' => 'a'],
            'B' => ['ok' => false, 'error' => 'down', 'host' => 'b'],
        ], 'A');
        self::assertSame('B', WigosActiveServerStore::aliasActivo(0));
    }

    public function test_curr_wigos_lee_store_por_empresa(): void
    {
        config([
            'wigos.curr_wigos' => 'A',
            'wigos.por_empresa' => [
                2 => [
                    'curr_wigos' => 'B',
                    'connections' => [
                        'A' => ['host' => 'ksaa'],
                        'B' => ['host' => 'ksab'],
                    ],
                ],
            ],
            'wigos.monitor_servidor_activo.habilitado' => true,
        ]);

        self::assertSame('B', WigosConfigResolver::currWigosConfigurado(2));
        self::assertSame('B', WigosConfigResolver::currWigos(2));

        // Preferido Kandiko es B; si B falla y A OK → publica A
        WigosActiveServerStore::registrarChequeos(2, [
            'A' => ['ok' => true, 'error' => null, 'host' => 'ksaa'],
            'B' => ['ok' => false, 'error' => 'restoring', 'host' => 'ksab'],
        ], 'B');

        self::assertSame('A', WigosActiveServerStore::aliasActivo(2));
        self::assertSame('A', WigosConfigResolver::currWigos(2));
        self::assertSame('B', WigosConfigResolver::currWigosConfigurado(2));
    }

    public function test_persiste_en_archivo_y_cache(): void
    {
        WigosActiveServerStore::registrarChequeos(0, [
            'A' => ['ok' => true, 'error' => null, 'host' => 'a'],
            'B' => ['ok' => false, 'error' => 'x', 'host' => 'b'],
        ], 'A');

        Cache::forget('wigos.active_server.0');

        self::assertSame('A', WigosActiveServerStore::aliasActivo(0));
        self::assertFileExists(storage_path('app/wigos/active_server/state.json'));
    }

    public function test_monitor_deshabilitado_no_expone_alias(): void
    {
        WigosActiveServerStore::registrarChequeos(0, [
            'A' => ['ok' => false, 'error' => 'x', 'host' => 'a'],
            'B' => ['ok' => true, 'error' => null, 'host' => 'b'],
        ], 'A');

        config()->set('wigos.monitor_servidor_activo.habilitado', false);
        self::assertNull(WigosActiveServerStore::aliasActivo(0));
        self::assertSame('A', WigosConfigResolver::currWigos(0));
    }
}

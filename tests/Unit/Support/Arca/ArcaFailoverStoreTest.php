<?php

namespace Tests\Unit\Support\Arca;

use App\Support\Arca\ArcaFailoverStore;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ArcaFailoverStoreTest extends TestCase
{
    protected function tearDown(): void
    {
        ArcaFailoverStore::reset(ArcaFailoverStore::WS_WSFE);
        ArcaFailoverStore::reset(ArcaFailoverStore::WS_MTXCA);

        parent::tearDown();
    }

    public function test_activa_failover_tras_umbral_de_fallos(): void
    {
        config()->set('arca.monitor_conectividad.fallos_para_activar', 2);
        config()->set('arca.monitor_conectividad.ok_para_desactivar', 2);

        self::assertFalse(ArcaFailoverStore::estaActivo(ArcaFailoverStore::WS_WSFE));

        ArcaFailoverStore::registrarChequeo(ArcaFailoverStore::WS_WSFE, false, 'Connection timed out');
        self::assertFalse(ArcaFailoverStore::estaActivo(ArcaFailoverStore::WS_WSFE));

        ArcaFailoverStore::registrarChequeo(ArcaFailoverStore::WS_WSFE, false, 'Connection timed out');
        self::assertTrue(ArcaFailoverStore::estaActivo(ArcaFailoverStore::WS_WSFE));
    }

    public function test_desactiva_failover_tras_umbral_de_ok(): void
    {
        config()->set('arca.monitor_conectividad.fallos_para_activar', 1);
        config()->set('arca.monitor_conectividad.ok_para_desactivar', 2);

        ArcaFailoverStore::registrarChequeo(ArcaFailoverStore::WS_WSFE, false, 'timeout');
        self::assertTrue(ArcaFailoverStore::estaActivo(ArcaFailoverStore::WS_WSFE));

        ArcaFailoverStore::registrarChequeo(ArcaFailoverStore::WS_WSFE, true);
        self::assertTrue(ArcaFailoverStore::estaActivo(ArcaFailoverStore::WS_WSFE));

        ArcaFailoverStore::registrarChequeo(ArcaFailoverStore::WS_WSFE, true);
        self::assertFalse(ArcaFailoverStore::estaActivo(ArcaFailoverStore::WS_WSFE));
    }

    public function test_persiste_en_archivo_y_cache(): void
    {
        config()->set('arca.monitor_conectividad.fallos_para_activar', 1);

        ArcaFailoverStore::registrarChequeo(ArcaFailoverStore::WS_WSFE, false, 'error red');
        Cache::forget('arca.failover.wsfe');

        self::assertTrue(ArcaFailoverStore::estaActivo(ArcaFailoverStore::WS_WSFE));
        self::assertFileExists(storage_path('app/arca/failover/state.json'));
    }
}

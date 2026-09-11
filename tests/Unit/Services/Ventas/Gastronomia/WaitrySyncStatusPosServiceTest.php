<?php

namespace Tests\Unit\Services\Ventas\Gastronomia;

use App\Jobs\EnviarWaitrySyncStatusPosJob;
use App\Models\Ventas\CuentaGastronomia;
use App\Services\Ventas\Gastronomia\Waitry\WaitrySyncStatusPosEnvioService;
use App\Services\Ventas\Gastronomia\Waitry\WaitrySyncStatusPosService;
use Tests\TestCase;


class WaitrySyncStatusPosServiceTest extends TestCase
{
    public function test_encolar_omitido_si_waitry_deshabilitado(): void
    {
        config(['waitry.habilitado' => false]);

        $svc = $this->app->make(WaitrySyncStatusPosService::class);
        $cuenta = $this->cuentaStub(99);

        $r = $svc->encolarPagoTrasFactura(
            $cuenta,
            [['cuentacaja_id' => 1, 'moneda_id' => 1, 'monto' => 100.0]],
            10,
        );

        $this->assertTrue($r['ok']);
        $this->assertTrue($r['omitida'] ?? false);
        $this->assertArrayNotHasKey('encolada', $r);
    }

    public function test_encolar_omitido_sin_waitry_order_id(): void
    {
        config(['waitry.habilitado' => true]);

        $svc = $this->app->make(WaitrySyncStatusPosService::class);
        $cuenta = $this->cuentaStub(0);

        $r = $svc->encolarPagoTrasFactura(
            $cuenta,
            [['cuentacaja_id' => 1, 'moneda_id' => 1, 'monto' => 100.0]],
            10,
        );

        $this->assertTrue($r['ok']);
        $this->assertTrue($r['omitida'] ?? false);
    }

    public function test_encolar_omitido_sin_medios_ni_venta(): void
    {
        config(['waitry.habilitado' => true]);

        $svc = $this->app->make(WaitrySyncStatusPosService::class);
        $cuenta = $this->cuentaStub(55);

        $r = $svc->encolarPagoTrasFactura($cuenta, [], 0);

        $this->assertTrue($r['ok']);
        $this->assertTrue($r['omitida'] ?? false);
    }

    public function test_sincronizar_omitido_si_deshabilitado(): void
    {
        config(['waitry.habilitado' => false]);

        $svc = $this->app->make(WaitrySyncStatusPosService::class);
        $r = $svc->sincronizarPagoTrasFactura(
            $this->cuentaStub(12),
            [['cuentacaja_id' => 1, 'moneda_id' => 1, 'monto' => 50.0]],
        );

        $this->assertTrue($r['ok']);
        $this->assertTrue($r['omitida'] ?? false);
    }

    public function test_cola_real_disponible_solo_con_driver_asincrono(): void
    {
        $svc = $this->app->make(WaitrySyncStatusPosEnvioService::class);

        config([
            'waitry.sync_status_pos_en_cola' => true,
            'waitry.encolar_reintentos' => true,
            'queue.default' => 'database',
        ]);
        $this->assertTrue($svc->colaRealDisponible());

        config(['queue.default' => 'sync']);
        $this->assertFalse($svc->colaRealDisponible());

        config(['queue.default' => 'database', 'waitry.sync_status_pos_en_cola' => false]);
        $this->assertFalse($svc->colaRealDisponible());
    }

    public function test_backoff_crece_con_el_intento(): void
    {
        $svc = $this->app->make(WaitrySyncStatusPosEnvioService::class);
        config(['waitry.reintento_backoff_segundos' => [30, 60, 120]]);

        $this->assertSame(30, $svc->segundosBackoff(1));
        $this->assertSame(60, $svc->segundosBackoff(2));
        $this->assertSame(120, $svc->segundosBackoff(9));
    }

    public function test_job_unique_id_por_registro(): void
    {
        $job = new EnviarWaitrySyncStatusPosJob(42);

        $this->assertSame('waitry-sync-status-pos-42', $job->uniqueId());
        $this->assertSame((string) config('waitry.cola', 'default'), $job->queue);
    }

    public function test_kds_updateexternal_se_omite_si_flag_deshabilitado(): void
    {
        config([
            'waitry.update_order_status_habilitado' => false,
            'waitry.update_order_status_url' => 'https://api.waitry.net/1/live/order/updateexternal',
        ]);

        $svc = $this->app->make(WaitrySyncStatusPosService::class);
        $ref = new \ReflectionMethod($svc, 'actualizarEstadoKds');
        $ref->setAccessible(true);

        $r = $ref->invoke($svc, 123456, 11782, 7);

        $this->assertTrue($r['ok']);
        $this->assertTrue($r['omitida'] ?? false);
    }

    private function cuentaStub(int $waitryOrderId): CuentaGastronomia
    {
        $cuenta = new CuentaGastronomia;
        $cuenta->id = 7;
        $cuenta->empresa_id = 1;
        $cuenta->waitry_order_id = $waitryOrderId;

        return $cuenta;
    }
}

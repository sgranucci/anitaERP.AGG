<?php

namespace App\Support\Configuracion;

use App\Models\Configuracion\AvisoRegla;
use App\Models\Configuracion\AvisoReglaConfig;
use App\Models\Configuracion\EmpresaWhatsappCuenta;
use App\Models\Configuracion\ModuloAvisoEventoSuscripcion;
use App\Models\Configuracion\WebPushSubscription;
use App\Models\Seguridad\Usuario;
use App\Services\Configuracion\AvisoReglaMotorService;
use App\Services\Configuracion\WebPushEnvioService;
use Illuminate\Support\Facades\Schema;

/**
 * Checklist operativo del pack avisos (pantalla index).
 */
final class ModuloAvisoOperativoEstadoSupport
{
    /**
     * @return list<array{clave: string, etiqueta: string, ok: bool, detalle: string, url: string|null}>
     */
    public static function checklist(): array
    {
        $driver = config('whatsapp.driver');
        $driverOk = $driver !== null && $driver !== '' && $driver !== false
            && ! in_array(strtolower(trim((string) $driver)), ['null', 'log', 'dry', 'none'], true);

        $waCuentas = Schema::hasTable('empresa_whatsapp_cuenta')
            ? (int) EmpresaWhatsappCuenta::query()->where('activo', true)->count()
            : 0;

        $celulares = Schema::hasColumn('usuario', 'celular')
            ? (int) Usuario::query()->whereNotNull('celular')->where('celular', '!=', '')->count()
            : 0;

        $motorVivo = false;
        try {
            $motorVivo = app(AvisoReglaMotorService::class)->motorHabilitado();
        } catch (\Throwable) {
        }
        $reglasActivas = Schema::hasTable('aviso_regla')
            ? (int) AvisoRegla::query()->where('activo', true)->count()
            : 0;
        $motorCfg = Schema::hasTable('aviso_regla_config')
            ? (bool) AvisoReglaConfig::actual()->motor_habilitado
            : false;

        $subsEvento = Schema::hasTable('modulo_aviso_evento_suscripcion')
            ? (int) ModuloAvisoEventoSuscripcion::query()->where('activo', true)->count()
            : 0;

        $pushOn = false;
        $pushSubs = 0;
        try {
            $pushOn = app(WebPushEnvioService::class)->habilitado();
            if (Schema::hasTable('web_push_subscription')) {
                $pushSubs = (int) WebPushSubscription::query()->count();
            }
        } catch (\Throwable) {
        }

        return [
            [
                'clave' => 'wa_driver',
                'etiqueta' => 'WhatsApp driver Meta',
                'ok' => $driverOk,
                'detalle' => $driverOk
                    ? 'WHATSAPP_DRIVER activo ('.(string) $driver.')'
                    : 'Hoy: '.var_export($driver, true).' — poner meta_cloud en producción',
                'url' => null,
            ],
            [
                'clave' => 'wa_cuentas',
                'etiqueta' => 'Cuentas WhatsApp activas',
                'ok' => $waCuentas > 0,
                'detalle' => $waCuentas > 0 ? $waCuentas.' empresa(s)' : 'Ninguna cuenta activa',
                'url' => 'consultar_empresa_whatsapp',
            ],
            [
                'clave' => 'celulares',
                'etiqueta' => 'Usuarios con celular',
                'ok' => $celulares > 0,
                'detalle' => $celulares > 0 ? $celulares.' usuario(s)' : 'Cargar celulares (masivo o ficha)',
                'url' => 'editar_usuarios_celular_masivo',
            ],
            [
                'clave' => 'eventos',
                'etiqueta' => 'Suscripciones de eventos',
                'ok' => $subsEvento > 0,
                'detalle' => $subsEvento > 0 ? $subsEvento.' activa(s)' : 'Opcional: sumar avisos extra por evento',
                'url' => 'consultar_modulo_aviso_evento',
            ],
            [
                'clave' => 'reglas',
                'etiqueta' => 'Motor de reglas',
                'ok' => $motorVivo && $reglasActivas > 0,
                'detalle' => $motorCfg
                    ? ($reglasActivas > 0
                        ? ($motorVivo ? $reglasActivas.' regla(s) activa(s)' : 'Config ON pero kill-switch .env')
                        : 'Motor ON sin reglas activas')
                    : 'Detenido (listo para activar cuando haga falta)',
                'url' => 'consultar_aviso_regla',
            ],
            [
                'clave' => 'push',
                'etiqueta' => 'Web Push',
                'ok' => $pushOn,
                'detalle' => $pushOn
                    ? ('VAPID OK · '.$pushSubs.' suscripción(es)')
                    : 'Faltan claves VAPID o está deshabilitado',
                'url' => 'editar_mis_preferencias_aviso',
            ],
            [
                'clave' => 'programador',
                'etiqueta' => 'Programador',
                'ok' => (bool) config('modulo_aviso.programados_habilitado', true)
                    && is_array(config('modulo_aviso.programados'))
                    && config('modulo_aviso.programados') !== [],
                'detalle' => (bool) config('modulo_aviso.programados_habilitado', true)
                    ? (count((array) config('modulo_aviso.programados', [])).' tareas · cockpit + historial')
                    : 'Despacho off (MODULO_AVISO_PROGRAMADOS)',
                'url' => 'consultar_modulo_aviso',
            ],
        ];
    }
}

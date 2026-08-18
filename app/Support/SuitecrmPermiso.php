<?php

namespace App\Support;

use App\Support\Cache\PermisoCacheSupport;

/**
 * Permisos SuiteCRM con fallback a clientes (caché de permisos puede estar desactualizada).
 */
final class SuitecrmPermiso
{
    public static function puedeVerSolapa(): bool
    {
        if (! self::integracionActiva()) {
            return false;
        }

        if (session()->get('rol_nombre') === 'administrador') {
            return true;
        }

        return can('listar-notas-suitecrm-cliente', false)
            || can('editar-clientes', false);
    }

    public static function integracionActiva(): bool
    {
        if ((bool) config('suitecrm.habilitado', false)) {
            return true;
        }

        $env = env('SUITECRM_HABILITADO');

        return filter_var($env, FILTER_VALIDATE_BOOLEAN)
            || $env === '1'
            || strtolower((string) $env) === 'true';
    }

    public static function puedeListarNotas(): void
    {
        if (can('listar-notas-suitecrm-cliente', false) || can('editar-clientes', false)) {
            return;
        }
        can('listar-notas-suitecrm-cliente');
    }

    public static function puedeGestionarNotas(): void
    {
        if (can('gestionar-notas-suitecrm-cliente', false) || can('actualizar-clientes', false)) {
            return;
        }
        can('gestionar-notas-suitecrm-cliente');
    }

    /**
     * Ver notas creadas en SuiteCRM por usuarios con rol Supervisor (restringidas al resto).
     */
    public static function puedeVerNotasSupervisor(): bool
    {
        if (session()->get('rol_nombre') === 'administrador') {
            return true;
        }

        return can('ver-notas-supervisor-suitecrm-cliente', false);
    }

    public static function puedeSincronizarCuenta(): bool
    {
        return can('sincronizar-cuenta-suitecrm-cliente', false)
            || can('actualizar-clientes', false);
    }

    public static function assertSincronizarCuenta(): void
    {
        if (self::puedeSincronizarCuenta()) {
            return;
        }
        can('sincronizar-cuenta-suitecrm-cliente');
    }

    public static function flushCachePermisos(): void
    {
        PermisoCacheSupport::flush();
    }
}

<?php

namespace App\Support\Admin;

use App\Support\Configuracion\ParametroSistemaSupport;

/**
 * Globo con la cantidad de opciones colgadas en cada submenú del aside.
 * Default de la instalación (Configuración general) + override opcional por usuario (cookie).
 */
final class MenuAsideConteoHijosSupport
{
    public const COOKIE = 'anita_aside_conteo_hijos';

    public static function defaultInstalacion(): bool
    {
        return ParametroSistemaSupport::menuAsideMostrarConteoHijos();
    }

    /**
     * Cookie 0/1 pisa el default; sin cookie → default de la instalación.
     */
    public static function mostrar(): bool
    {
        $cookie = request()->cookie(self::COOKIE);
        if ($cookie === '0') {
            return false;
        }
        if ($cookie === '1') {
            return true;
        }

        return self::defaultInstalacion();
    }

    public static function tieneOverrideUsuario(): bool
    {
        $cookie = request()->cookie(self::COOKIE);

        return $cookie === '0' || $cookie === '1';
    }
}

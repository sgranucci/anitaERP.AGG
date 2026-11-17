<?php

namespace App\Support\ReglaNegocio;

use App\Services\Configuracion\ReglaNegocioMotorService;

/**
 * Consulta segura al BRE sync: null = usar legacy (motor OFF, sin match o error).
 * Seguro en tests PHPUnit puros (sin container Laravel).
 */
final class ReglaNegocioConsultaSupport
{
    /**
     * @param  array<string, mixed>  $contexto
     */
    public static function decidirSiActivo(string $punto, array $contexto = []): ?ReglaNegocioDecision
    {
        $punto = trim($punto);
        if ($punto === '') {
            return null;
        }

        try {
            if (! function_exists('app') || ! class_exists(ReglaNegocioMotorService::class)) {
                return null;
            }
            $app = app();
            if (! is_object($app)) {
                return null;
            }
            /** @var ReglaNegocioMotorService $motor */
            $motor = $app->make(ReglaNegocioMotorService::class);
            if (! $motor->motorHabilitado()) {
                return null;
            }
            $d = $motor->decidir($punto, $contexto);

            return $d->evaluado ? $d : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function boolDato(array $datos, string $clave): ?bool
    {
        if (! array_key_exists($clave, $datos)) {
            return null;
        }

        return filter_var($datos[$clave], FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function floatDato(array $datos, string $clave): ?float
    {
        if (! array_key_exists($clave, $datos) || ! is_numeric($datos[$clave])) {
            return null;
        }

        return (float) $datos[$clave];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function intDato(array $datos, string $clave): ?int
    {
        if (! array_key_exists($clave, $datos) || ! is_numeric($datos[$clave])) {
            return null;
        }

        return (int) $datos[$clave];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function stringDato(array $datos, string $clave): ?string
    {
        if (! array_key_exists($clave, $datos) || $datos[$clave] === null) {
            return null;
        }

        return (string) $datos[$clave];
    }
}

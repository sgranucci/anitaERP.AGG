<?php

namespace App\Support\Caja\Flash;

use App\Models\Caja\Flash\FlashCaja;

/**
 * Validación manual del flash diario (tilde verde en Contable).
 * Habilitados: mbarrios, sergio, admin, y cualquier sesión con rol administrador.
 */
final class FlashCajaValidacionSupport
{
    /**
     * @return list<string>
     */
    public static function usuariosHabilitados(): array
    {
        $raw = config('caja.flash_validacion.usuarios', []);
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }

        $out = ['mbarrios', 'sergio', 'admin'];
        foreach ((array) $raw as $login) {
            $login = strtolower(trim((string) $login));
            if ($login !== '') {
                $out[] = $login;
            }
        }

        return array_values(array_unique($out));
    }

    public static function loginActual(): string
    {
        $authLogin = strtolower(trim((string) (auth()->user()?->usuario ?? '')));
        if ($authLogin !== '') {
            return $authLogin;
        }

        return strtolower(trim((string) (session('usuario') ?? '')));
    }

    public static function usuarioPuedeValidar(?string $login = null): bool
    {
        $usarSesion = $login === null;
        $login = strtolower(trim((string) ($login ?? self::loginActual())));
        if ($login !== '' && in_array($login, self::usuariosHabilitados(), true)) {
            return true;
        }

        if (! $usarSesion) {
            return false;
        }

        return self::sesionEsAdministrador();
    }

    public static function sesionEsAdministrador(): bool
    {
        if (strtolower(trim((string) session('rol_nombre'))) === 'administrador') {
            return true;
        }

        foreach ((array) session('roles', []) as $rol) {
            $nombre = is_array($rol) ? (string) ($rol['nombre'] ?? '') : (string) $rol;
            if (strtolower(trim($nombre)) === 'administrador') {
                return true;
            }
        }

        return false;
    }

    public static function estaValidado(?FlashCaja $flash): bool
    {
        if ($flash === null) {
            return false;
        }

        return (bool) ($flash->validado ?? false);
    }

    /**
     * @return array<string, bool> Y-m-d => validado
     */
    public static function mapaValidadoPorFecha(int $empresaId, string $desde, string $hasta): array
    {
        if ($empresaId <= 0 || $desde === '' || $hasta === '') {
            return [];
        }

        $out = [];
        FlashCaja::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->get(['fecha', 'validado'])
            ->each(function (FlashCaja $flash) use (&$out) {
                $fecha = $flash->fecha?->format('Y-m-d');
                if ($fecha === null || $fecha === '') {
                    return;
                }
                $out[$fecha] = self::estaValidado($flash);
            });

        return $out;
    }

    public static function marcarValidado(FlashCaja $flash, int $usuarioId): void
    {
        $flash->validado = true;
        $flash->validado_en = now();
        $flash->validado_usuario_id = $usuarioId > 0 ? $usuarioId : null;
        $flash->save();
    }

    public static function quitarValidacion(FlashCaja $flash): void
    {
        $flash->validado = false;
        $flash->validado_en = null;
        $flash->validado_usuario_id = null;
        $flash->save();
    }
}

<?php

namespace App\Support\Caja\Flash;

use App\Models\Caja\Flash\FlashCaja;

/**
 * Validación manual del flash diario. Por ahora solo mbarrios puede marcarla.
 */
final class FlashCajaValidacionSupport
{
    /**
     * @return list<string>
     */
    public static function usuariosHabilitados(): array
    {
        $raw = config('caja.flash_validacion.usuarios', ['mbarrios']);
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }

        $out = [];
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
        return strtolower(trim((string) (
            session('usuario')
            ?? auth()->user()?->usuario
            ?? ''
        )));
    }

    public static function usuarioPuedeValidar(?string $login = null): bool
    {
        $login = strtolower(trim((string) ($login ?? self::loginActual())));
        if ($login === '') {
            return false;
        }

        return in_array($login, self::usuariosHabilitados(), true);
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

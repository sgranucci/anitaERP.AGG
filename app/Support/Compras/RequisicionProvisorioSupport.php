<?php

namespace App\Support\Compras;

use App\Models\Compras\Requisicion_Estado;

/**
 * Modo provisorio (borrador): grabar sin árbol ni Anita; confirmar después.
 * Se activa por permiso {@see PERMISO_MODO_PROVISORIO}.
 */
final class RequisicionProvisorioSupport
{
    public const PERMISO_MODO_PROVISORIO = 'guardar-requisicion-provisorio';

    public const PERMISO_CONFIRMAR = 'confirmar-requisicion';

    public static function usuarioUsaModoProvisorio(): bool
    {
        return can(self::PERMISO_MODO_PROVISORIO, false);
    }

    public static function nombreEstadoProvisorio(): string
    {
        return self::resolverNombrePorValor('V');
    }

    public static function esEstadoProvisorio(?string $estado): bool
    {
        return trim((string) $estado) === self::nombreEstadoProvisorio();
    }

    private static function resolverNombrePorValor(string $valor): string
    {
        $idx = array_search($valor, array_column(Requisicion_Estado::$enumEstado, 'valor'), true);
        if ($idx === false) {
            return 'PROVISORIO';
        }

        return Requisicion_Estado::$enumEstado[$idx]['nombre'];
    }
}

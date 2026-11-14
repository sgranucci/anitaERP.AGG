<?php

namespace App\Support\Caja;

/**
 * Formatos del Anexo I: contable (día/mes/anual, l-anexo1.c) y debe/haber (l-anexo1new.c).
 *
 * El formato contable exige `listar-tesoreria-crown-anexo1-contable`.
 * Sin ese permiso (roles de tesorería) solo queda debe/haber.
 */
final class TesoreriaCrownAnexo1FormatoSupport
{
    public const CLASICO = 'clasico';

    public const NUEVO = 'nuevo';

    public const PERMISO_CONTABLE = 'listar-tesoreria-crown-anexo1-contable';

    public static function puedeFormatoContable(): bool
    {
        return can(self::PERMISO_CONTABLE, false);
    }

    /**
     * Resuelve el formato pedido; sin permiso contable fuerza debe/haber.
     */
    public static function resolver(?string $pedido): string
    {
        $pedido = strtolower(trim((string) $pedido));
        if ($pedido === self::NUEVO) {
            return self::NUEVO;
        }
        if ($pedido === self::CLASICO || $pedido === '' || $pedido === 'contable') {
            return self::puedeFormatoContable() ? self::CLASICO : self::NUEVO;
        }

        return self::puedeFormatoContable() ? self::CLASICO : self::NUEVO;
    }
}

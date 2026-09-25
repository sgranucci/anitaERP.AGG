<?php

declare(strict_types=1);

namespace App\Support\Ventas\Ferli;

use App\Support\Configuracion\EntornoEmpresaSupport;
use RuntimeException;

/**
 * Bloqueo de altas de pedido y OT en la instancia L8 (legacy) de Ferli.
 *
 * Activo si:
 * - FERLI_L8_BLOQUEAR_ALTAS_PEDIDO_OT=true, o
 * - Ferli + BD operativa exactamente `anitaERP` (nombre histórico de L8; L12 usa `anitaERP_l12`).
 *
 * En L12 (anitaERP_l12) no bloquea. Solo corta create; update/consulta siguen.
 */
final class FerliL8AltasBloqueadasSupport
{
    public static function activo(): bool
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return false;
        }

        $raw = config('ferli_l8.bloquear_altas_pedido_ot');
        if ($raw !== null && $raw !== '') {
            return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
        }

        // Auto: L8 legacy = misma app Ferli con schema `anitaERP` (sin sufijo _l12).
        $db = (string) config('database.connections.'.config('database.default').'.database');

        return $db === 'anitaERP';
    }

    public static function mensajePedido(): string
    {
        return 'L8 está en solo lectura para pedidos: no se pueden dar altas. '
            .'Cargue el pedido en el ERP nuevo (L12 / anitaerp.ferli.com.ar).';
    }

    public static function mensajeOt(): string
    {
        return 'L8 está en solo lectura para órdenes de trabajo: no se pueden dar altas. '
            .'Genere la OT en el ERP nuevo (L12 / anitaerp.ferli.com.ar).';
    }

    /** @return array{error: string}|null */
    public static function errorSiNoPuedeCrearPedido(): ?array
    {
        return self::activo() ? ['error' => self::mensajePedido()] : null;
    }

    /** @return array{error: string}|null */
    public static function errorSiNoPuedeCrearOt(): ?array
    {
        return self::activo() ? ['error' => self::mensajeOt()] : null;
    }

    public static function assertPuedeCrearPedido(): void
    {
        if (self::activo()) {
            throw new RuntimeException(self::mensajePedido());
        }
    }

    public static function assertPuedeCrearOt(): void
    {
        if (self::activo()) {
            throw new RuntimeException(self::mensajeOt());
        }
    }
}

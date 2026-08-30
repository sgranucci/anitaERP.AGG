<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Puntoventa;

/**
 * Empresa jurídica de la sucursal (punto de venta) elegido.
 */
final class PuntoventaEmpresaSupport
{
    public static function empresaId(?int $puntoventaId): ?int
    {
        if ($puntoventaId === null || $puntoventaId <= 0) {
            return null;
        }

        $id = (int) Puntoventa::query()->whereKey($puntoventaId)->value('empresa_id');

        return $id > 0 ? $id : null;
    }

    /**
     * Sucursal de facturación del usuario (la misma que el modal de emitir).
     */
    public static function empresaIdDesdePreferenciaFacturacion(?int $usuarioId = null): ?int
    {
        $pv = UsuarioPreferenciaFacturacionSupport::leer($usuarioId)['puntoventa_id'] ?? null;

        return self::empresaId($pv !== null ? (int) $pv : null);
    }
}

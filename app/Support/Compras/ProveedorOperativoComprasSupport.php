<?php

namespace App\Support\Compras;

use App\Models\Compras\Proveedor;

/**
 * Proveedor habilitado para requisición y orden de compra (Activo / Regularizado).
 * Los suspendidos se muestran en la lupa en rojo, pero no se pueden asignar.
 */
final class ProveedorOperativoComprasSupport
{
    public static function mensajeNoOperativo(?string $estado = null): string
    {
        if (trim((string) $estado) === Proveedor::ESTADO_SUSPENDIDO) {
            return 'El proveedor está suspendido (tiene un problema, p. ej. padrón ARCA). No se puede usar en requisiciones ni órdenes de compra: contactá a Compras.';
        }

        return 'El proveedor no está habilitado para operar. No se puede usar en requisiciones ni órdenes de compra: contactá a Compras.';
    }

    public static function esOperativo(?Proveedor $proveedor): bool
    {
        if ($proveedor === null) {
            return true;
        }

        return Proveedor::estaHabilitadoParaOperacion((string) ($proveedor->estado ?? ''));
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function assertProveedorOperativo(?Proveedor $proveedor): void
    {
        if (self::esOperativo($proveedor)) {
            return;
        }

        throw new \InvalidArgumentException(self::mensajeNoOperativo((string) ($proveedor->estado ?? '')));
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function assertIdOperativo(int|string|null $proveedorId): void
    {
        $id = (int) $proveedorId;
        if ($id <= 0) {
            return;
        }

        self::assertProveedorOperativo(Proveedor::query()->find($id));
    }
}

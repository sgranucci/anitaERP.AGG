<?php

namespace App\Support\Ventas;

/**
 * Precio editable en remitos de venta (permiso especial).
 */
final class RemitoPrecioEditableSupport
{
    public const PERMISO = 'modificar-precio-remito';

    public static function puedeModificarPrecio(): bool
    {
        return can(self::PERMISO, false);
    }

    /**
     * Sin permiso: conserva el precio ya grabado en líneas existentes.
     * Las líneas nuevas mantienen el precio enviado (viene de lista / asignapreciocliente).
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lineasExistentes  filas remito_articulo (update)
     * @return array<string, mixed>
     */
    public static function aplicarPreciosSegunPermiso(array $data, string $funcion, array $lineasExistentes = []): array
    {
        if (self::puedeModificarPrecio()) {
            return $data;
        }

        if (! isset($data['precios']) || ! is_array($data['precios'])) {
            return $data;
        }

        if ($funcion !== 'update' || $lineasExistentes === []) {
            return $data;
        }

        $porId = [];
        foreach ($lineasExistentes as $linea) {
            $lineaId = (int) ($linea['id'] ?? 0);
            if ($lineaId > 0) {
                $porId[$lineaId] = (float) ($linea['precio'] ?? 0);
            }
        }

        $ids = $data['ids'] ?? [];
        if (! is_array($ids)) {
            return $data;
        }

        foreach ($data['precios'] as $i => $precioEnviado) {
            $lineaId = (int) ($ids[$i] ?? 0);
            if ($lineaId > 0 && array_key_exists($lineaId, $porId)) {
                $data['precios'][$i] = $porId[$lineaId];
            }
        }

        return $data;
    }
}

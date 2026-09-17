<?php

namespace App\Support\Configuracion;

use App\Models\Configuracion\Condicioniva;
use Illuminate\Support\Collection;

/**
 * Letra de comprobante según condición IVA en contexto COMPRAS / proveedor.
 *
 * Ventas: Monotributo = A (nosotros emitimos FAC A al monotributista).
 * Compras: Monotributo = C (el proveedor monotributista nos emite FAC C).
 *
 * No modificar condicioniva.letra del maestro (compartido con clientes).
 */
final class CondicionivaLetraComprasSupport
{
    public static function condicionivaMonotributoId(): int
    {
        return (int) config('arca.padron_validacion_proveedor.condicioniva_monotributo_id', 4);
    }

    public static function letra(?Condicioniva $condicioniva): string
    {
        if ($condicioniva === null) {
            return '';
        }

        $letra = strtoupper(substr(trim((string) ($condicioniva->letra ?? '')), 0, 1));

        if (self::esMonotributo($condicioniva)) {
            return 'C';
        }

        return $letra;
    }

    public static function letraDesdeId(?int $condicionivaId): string
    {
        if ($condicionivaId === null || $condicionivaId <= 0) {
            return '';
        }

        $condicioniva = Condicioniva::query()->find($condicionivaId);

        return self::letra($condicioniva);
    }

    public static function esMonotributo(?Condicioniva $condicioniva): bool
    {
        if ($condicioniva === null) {
            return false;
        }

        if ((int) $condicioniva->id === self::condicionivaMonotributoId()) {
            return true;
        }

        $nombre = mb_strtolower(trim((string) ($condicioniva->nombre ?? '')));

        return str_contains($nombre, 'monotribut');
    }

    /**
     * Copia la colección de condiciones IVA con letra ajustada para formularios de proveedor.
     * No muta filas de BD ni la colección original.
     *
     * @param  Collection<int, Condicioniva>|iterable<Condicioniva>  $condiciones
     * @return Collection<int, Condicioniva>
     */
    public static function coleccionConLetraCompras(iterable $condiciones): Collection
    {
        return collect($condiciones)->map(static function (Condicioniva $fila): Condicioniva {
            $copia = $fila->replicate();
            $copia->id = $fila->id;
            $copia->exists = true;
            $copia->letra = self::letra($fila);

            return $copia;
        })->values();
    }
}

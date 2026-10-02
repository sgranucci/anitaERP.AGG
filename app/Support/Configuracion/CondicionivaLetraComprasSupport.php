<?php

namespace App\Support\Configuracion;

use App\Models\Configuracion\Condicioniva;
use Illuminate\Support\Collection;

/**
 * Letra de comprobante según condición IVA en contexto COMPRAS / proveedor.
 *
 * condicioniva.letra es la de ventas (lo que nosotros emitimos al cliente).
 * condicioniva.letra_compras es la que emite el proveedor (RG AFIP 1415, arts. 15 y 16):
 * monotributista y exento emiten C. No pisar letra: la comparten los clientes.
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

        $guardada = strtoupper(substr(trim((string) ($condicioniva->letra_compras ?? '')), 0, 1));
        if ($guardada !== '') {
            return $guardada;
        }

        return self::letraComprasPorNombre(
            (string) ($condicioniva->nombre ?? ''),
            (string) ($condicioniva->letra ?? '')
        );
    }

    /**
     * Valor inicial de letra_compras a partir del nombre y de la letra de ventas.
     */
    public static function letraComprasPorNombre(string $nombre, string $letraVentas): string
    {
        $nombreNorm = mb_strtolower(trim($nombre));
        $letra = strtoupper(substr(trim($letraVentas), 0, 1));

        if (str_contains($nombreNorm, 'monotribut')) {
            return 'C';
        }

        if ($nombreNorm !== '' && ! str_contains($nombreNorm, 'export') && str_contains($nombreNorm, 'exento')) {
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
     * Exento en IVA emite comprobante C. «Exento exportación» queda en E.
     */
    public static function esExento(?Condicioniva $condicioniva): bool
    {
        if ($condicioniva === null) {
            return false;
        }

        $nombre = mb_strtolower(trim((string) ($condicioniva->nombre ?? '')));
        if ($nombre === '' || str_contains($nombre, 'export')) {
            return false;
        }

        return str_contains($nombre, 'exento');
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

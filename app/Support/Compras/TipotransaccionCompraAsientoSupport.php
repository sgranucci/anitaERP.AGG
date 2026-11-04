<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Tipotransaccion_Compra;

/**
 * Flag del ABM tipo de transacción compra: asientocontable S/N.
 * S = genera asiento (y ctamov). N = solo cuenta corriente / circuito interno.
 */
final class TipotransaccionCompraAsientoSupport
{
    public const GENERA = 'S';

    public const NO_GENERA = 'N';

    public static function generaValor(mixed $asientocontable): bool
    {
        // Solo "N" = no genera. S, U (legado bienes de uso) u otro valor ≠ N sí generan.
        return strtoupper(trim((string) ($asientocontable ?? ''))) !== self::NO_GENERA;
    }

    public static function generaDesdeTipo(?Tipotransaccion_Compra $tipo): bool
    {
        if ($tipo === null) {
            // Sin tipo aún: el circuito normal asume asiento (la mayoría son S).
            return true;
        }

        return self::generaValor($tipo->asientocontable ?? null);
    }

    public static function generaDesdeTipoId(int $tipoId): bool
    {
        if ($tipoId <= 0) {
            return true;
        }

        $valor = Tipotransaccion_Compra::query()
            ->whereKey($tipoId)
            ->value('asientocontable');

        return self::generaValor($valor);
    }

    public static function generaDesdeComprobante(Comprobante_Proveedor $comprobante): bool
    {
        $comprobante->loadMissing('tipotransaccion_compras');

        return self::generaDesdeTipo($comprobante->tipotransaccion_compras);
    }

    /**
     * Mapa id → S|N para el formulario (toggle de solapa / preview).
     *
     * @param  iterable<int, object{id?: mixed, asientocontable?: mixed}>  $tipos
     * @return array<int, string>
     */
    public static function mapaDesdeColeccion(iterable $tipos): array
    {
        $out = [];
        foreach ($tipos as $tipo) {
            $id = (int) ($tipo->id ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[$id] = self::generaValor($tipo->asientocontable ?? null)
                ? self::GENERA
                : self::NO_GENERA;
        }

        return $out;
    }
}

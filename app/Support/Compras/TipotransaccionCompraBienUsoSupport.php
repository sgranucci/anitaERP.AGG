<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Tipotransaccion_Compra;

/**
 * Flag ERP `genera_bien_de_uso` (Anita tcomp_genera_asi = U).
 * En factprov.c / a-compprov.c, tras grabar el comprobante se ofrece
 * cargar el bien en a-biemae si in_genera_asiento == 'U'.
 */
final class TipotransaccionCompraBienUsoSupport
{
    public static function generaValor(mixed $valor): bool
    {
        return TipotransaccionCompraGeneraAsiAnitaSupport::generaBienDeUsoValor($valor);
    }

    public static function generaDesdeTipo(?Tipotransaccion_Compra $tipo): bool
    {
        if ($tipo === null) {
            return false;
        }

        return self::generaValor($tipo->genera_bien_de_uso ?? null);
    }

    public static function generaDesdeTipoId(int $tipoId): bool
    {
        if ($tipoId <= 0) {
            return false;
        }

        $valor = Tipotransaccion_Compra::query()
            ->whereKey($tipoId)
            ->value('genera_bien_de_uso');

        return self::generaValor($valor);
    }

    public static function generaDesdeComprobante(Comprobante_Proveedor $comprobante): bool
    {
        $comprobante->loadMissing('tipotransaccion_compras');

        return self::generaDesdeTipo($comprobante->tipotransaccion_compras);
    }

    /**
     * @param  iterable<int, object{id?: mixed, genera_bien_de_uso?: mixed}>  $tipos
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
            $out[$id] = self::generaValor($tipo->genera_bien_de_uso ?? null)
                ? TipotransaccionCompraGeneraAsiAnitaSupport::ERP_SI
                : TipotransaccionCompraGeneraAsiAnitaSupport::ERP_NO;
        }

        return $out;
    }
}

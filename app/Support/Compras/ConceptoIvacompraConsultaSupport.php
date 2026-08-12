<?php

namespace App\Support\Compras;

use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Compras\Tipotransaccion_Compra_Concepto_Ivacompra;
use Illuminate\Support\Collection;

/**
 * Lista conceptos IVA compra filtrados por tipo de comprobante (pivot).
 */
final class ConceptoIvacompraConsultaSupport
{
    /**
     * @return Collection<int, Concepto_Ivacompra>
     */
    public static function listarPorTipoTransaccion(int $tipotransaccionCompraId, ?string $consulta = null): Collection
    {
        if ($tipotransaccionCompraId <= 0) {
            return collect();
        }

        $conceptoIds = Tipotransaccion_Compra_Concepto_Ivacompra::query()
            ->where('tipotransaccion_compra_id', $tipotransaccionCompraId)
            ->pluck('concepto_ivacompra_id')
            ->unique()
            ->filter()
            ->values()
            ->all();

        if ($conceptoIds === []) {
            return collect();
        }

        $query = Concepto_Ivacompra::query()->whereIn('id', $conceptoIds);

        $texto = trim((string) $consulta);
        if ($texto !== '') {
            $query->where(function ($q) use ($texto) {
                $q->where('codigo', 'like', '%'.$texto.'%')
                    ->orWhere('nombre', 'like', '%'.$texto.'%')
                    ->orWhere('nombre_ia', 'like', '%'.$texto.'%');
            });
        }

        return $query->orderBy('nombre')->get();
    }

    public static function resolverPorCodigoOId(int $tipotransaccionCompraId, string $valor): ?Concepto_Ivacompra
    {
        $valor = trim($valor);
        if ($tipotransaccionCompraId <= 0 || $valor === '') {
            return null;
        }

        $lista = self::listarPorTipoTransaccion($tipotransaccionCompraId, null);
        if ($lista->isEmpty()) {
            return null;
        }

        if (ctype_digit($valor)) {
            $porId = $lista->firstWhere('id', (int) $valor);
            if ($porId) {
                return $porId;
            }
            $porCodigoExacto = $lista->first(fn (Concepto_Ivacompra $c) => (string) $c->codigo === $valor);
            if ($porCodigoExacto) {
                return $porCodigoExacto;
            }
        }

        return $lista->first(fn (Concepto_Ivacompra $c) => (string) $c->codigo === $valor);
    }

    public static function tipoTieneConceptosConfigurados(int $tipotransaccionCompraId): bool
    {
        if ($tipotransaccionCompraId <= 0) {
            return false;
        }

        return Tipotransaccion_Compra_Concepto_Ivacompra::query()
            ->where('tipotransaccion_compra_id', $tipotransaccionCompraId)
            ->exists();
    }
}

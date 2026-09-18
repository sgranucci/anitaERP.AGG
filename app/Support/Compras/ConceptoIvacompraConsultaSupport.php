<?php

namespace App\Support\Compras;

use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Models\Compras\Tipotransaccion_Compra_Concepto_Ivacompra;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorProrrateoMultiCcSupport;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Support\Collection;

/**
 * Lista conceptos IVA compra del tipo de comprobante (pivot).
 * Tipos prorrateados (FPB/CPB/…): el F1 lista la unión de los finos de la OC;
 * la plantilla de renglones no se arma (van los de la precarga).
 */
final class ConceptoIvacompraConsultaSupport
{
    /**
     * @return Collection<int, Concepto_Ivacompra>
     */
    public static function listarPorTipoTransaccion(
        int $tipotransaccionCompraId,
        ?string $consulta = null,
        ?string $numeroOc = null,
    ): Collection {
        if ($tipotransaccionCompraId <= 0) {
            return collect();
        }

        $conceptoIds = self::idsConceptoParaTipo($tipotransaccionCompraId, $numeroOc);
        if ($conceptoIds === []) {
            return collect();
        }

        $query = Concepto_Ivacompra::query()
            ->with(['impuestos', 'concepto_ivacompra_empresas'])
            ->whereIn('id', $conceptoIds);

        $texto = trim((string) $consulta);
        if ($texto !== '') {
            $query->where(function ($q) use ($texto) {
                $q->where('codigo', 'like', '%'.$texto.'%')
                    ->orWhere('nombre', 'like', '%'.$texto.'%')
                    ->orWhere('nombre_ia', 'like', '%'.$texto.'%');
            });
        }

        // Dedup por id (unión multi-fino puede repetir el mismo concepto).
        return $query
            ->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))
            ->orderBy('nombre')
            ->get()
            ->unique('id')
            ->values();
    }

    public static function resolverPorCodigoOId(
        int $tipotransaccionCompraId,
        string $valor,
        ?string $numeroOc = null,
    ): ?Concepto_Ivacompra {
        $valor = trim($valor);
        if ($tipotransaccionCompraId <= 0 || $valor === '') {
            return null;
        }

        $lista = self::listarPorTipoTransaccion($tipotransaccionCompraId, null, $numeroOc);
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

    public static function tipoTieneConceptosConfigurados(
        int $tipotransaccionCompraId,
        ?string $numeroOc = null,
    ): bool {
        if ($tipotransaccionCompraId <= 0) {
            return false;
        }

        if (Tipotransaccion_Compra_Concepto_Ivacompra::query()
            ->where('tipotransaccion_compra_id', $tipotransaccionCompraId)
            ->exists()) {
            return true;
        }

        return self::idsConceptoParaTipo($tipotransaccionCompraId, $numeroOc) !== [];
    }

    /**
     * Plantilla de renglones ($0) con todos los conceptos asignados al tipo.
     * En tipos prorrateados no hay plantilla: se usan los renglones de la precarga (unión).
     *
     * @return Collection<int, \App\Models\Compras\Comprobante_Proveedor_Concepto>
     */
    public static function renglonesPlantillaParaTipo(
        int $tipotransaccionCompraId,
        ?string $numeroOc = null,
    ): Collection {
        if ($tipotransaccionCompraId <= 0) {
            return collect();
        }

        $tipo = Tipotransaccion_Compra::query()->find($tipotransaccionCompraId);
        $abrev = strtoupper(trim((string) ($tipo->abreviatura ?? '')));
        if ($tipo && PrecargaProveedorProrrateoMultiCcSupport::esTipoProrrateado($abrev)) {
            return collect();
        }

        $lista = self::listarPorTipoTransaccion($tipotransaccionCompraId, null, $numeroOc);
        if ($lista->isEmpty()) {
            return collect();
        }

        return $lista->values()->map(function (Concepto_Ivacompra $concepto, int $idx) {
            $renglon = new \App\Models\Compras\Comprobante_Proveedor_Concepto([
                'concepto_ivacompra_id' => $concepto->id,
                'monto' => 0,
                'orden' => $idx + 1,
            ]);
            $renglon->setRelation('concepto_ivacompras', $concepto);

            return $renglon;
        });
    }

    /**
     * IDs únicos: pivot del tipo, o unión de finos origen si es FxP* y hay OC.
     *
     * @return list<int>
     */
    public static function idsConceptoParaTipo(int $tipotransaccionCompraId, ?string $numeroOc = null): array
    {
        if ($tipotransaccionCompraId <= 0) {
            return [];
        }

        $desdePivot = Tipotransaccion_Compra_Concepto_Ivacompra::query()
            ->where('tipotransaccion_compra_id', $tipotransaccionCompraId)
            ->pluck('concepto_ivacompra_id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($desdePivot !== []) {
            return $desdePivot;
        }

        $numeroOc = trim((string) $numeroOc);
        if ($numeroOc === '') {
            return [];
        }

        $tipo = Tipotransaccion_Compra::query()->find($tipotransaccionCompraId);
        if (! $tipo) {
            return [];
        }

        $abrev = strtoupper(trim((string) ($tipo->abreviatura ?? '')));
        if (! PrecargaProveedorProrrateoMultiCcSupport::esTipoProrrateado($abrev)) {
            return [];
        }

        try {
            $ids = app(PrecargaProveedorProrrateoMultiCcSupport::class)
                ->idsPermitidosParaTipo($tipo, $numeroOc);
        } catch (\Throwable) {
            return [];
        }

        return array_values(array_unique(array_map('intval', array_filter($ids))));
    }
}

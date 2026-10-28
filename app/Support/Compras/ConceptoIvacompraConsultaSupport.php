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
 * Tipos prorrateados (FPB/CPB/…): el F1 y la plantilla vacía listan la unión
 * de los finos de la OC. Si la precarga ya trae renglones, esos mandan.
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
            ->with([
                'impuestos',
                'cuentacontablesdebe:id,codigo,nombre',
                'concepto_ivacompra_empresas.cuentacontabledebe:id,codigo,nombre',
            ])
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

        // El operador tipea el código Anita (50 = Gravado 21). Ese código puede
        // coincidir con el id interno de otro concepto del mismo tipo (id 50 = IVA 27).
        // El código exacto manda; el id solo si ningún concepto tiene ese código.
        $porCodigoExacto = $lista->first(fn (Concepto_Ivacompra $c) => (string) $c->codigo === $valor);
        if ($porCodigoExacto) {
            return $porCodigoExacto;
        }

        if (ctype_digit($valor)) {
            return $lista->firstWhere('id', (int) $valor);
        }

        return null;
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
     * Plantilla de renglones ($0) con los conceptos del tipo.
     * En prorrateadas (FPB/CPB/…) es la unión de los finos de la OC, también en $0,
     * para poder cargar montos cuando la precarga no trajo renglones.
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
            return self::conIvaDelHermanoIndirecto($tipotransaccionCompraId, $desdePivot);
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

    /**
     * Factura directa de bienes (FDB) y las de locación (FDL): Anita no les vinculó
     * conceptos de IVA. El débito de bienes (DDB) y la factura indirecta (FIB/FIL)
     * sí tienen el IVA de esa base. Sin eso el combo no ofrece alícuota y el importe
     * de IVA termina en «No gravado».
     *
     * Solo cuando el tipo es directo (2ª letra D), tiene gravados y ningún IVA.
     *
     * @param  list<int>  $idsPivot
     * @return list<int>
     */
    public static function conIvaDelHermanoIndirecto(int $tipotransaccionCompraId, array $idsPivot): array
    {
        $idsPivot = array_values(array_unique(array_filter(array_map('intval', $idsPivot))));
        if ($tipotransaccionCompraId <= 0 || $idsPivot === []) {
            return $idsPivot;
        }

        $abrev = strtoupper(trim((string) (Tipotransaccion_Compra::query()
            ->whereKey($tipotransaccionCompraId)
            ->value('abreviatura') ?? '')));
        $hermano = self::abreviaturaHermanoIndirecto($abrev);
        if ($hermano === null) {
            return $idsPivot;
        }

        $propios = Concepto_Ivacompra::query()
            ->whereIn('id', $idsPivot)
            ->get(['id', 'codigo', 'tipoconcepto']);
        $codigosGravado = [];
        foreach ($propios as $concepto) {
            if (strtoupper(trim((string) ($concepto->tipoconcepto ?? ''))) === 'I') {
                return $idsPivot;
            }
            if (strtoupper(trim((string) ($concepto->tipoconcepto ?? ''))) === 'G') {
                $codigosGravado[(string) $concepto->codigo] = true;
            }
        }
        if ($codigosGravado === []) {
            return $idsPivot;
        }

        $hermanoId = (int) (Tipotransaccion_Compra::query()
            ->where('abreviatura', $hermano)
            ->value('id') ?? 0);
        if ($hermanoId <= 0) {
            return $idsPivot;
        }

        $idsHermano = Tipotransaccion_Compra_Concepto_Ivacompra::query()
            ->where('tipotransaccion_compra_id', $hermanoId)
            ->pluck('concepto_ivacompra_id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
        if ($idsHermano === []) {
            return $idsPivot;
        }

        $ivas = Concepto_Ivacompra::query()
            ->whereIn('id', $idsHermano)
            ->where('tipoconcepto', 'I')
            ->get(['id', 'formula']);

        $extra = [];
        foreach ($ivas as $iva) {
            foreach (self::codigosEnFormula((string) ($iva->formula ?? '')) as $codigo) {
                if (isset($codigosGravado[$codigo]) || isset($codigosGravado[ltrim($codigo, '0')])) {
                    $extra[] = (int) $iva->id;
                    break;
                }
            }
        }
        if ($extra === []) {
            return $idsPivot;
        }

        return array_values(array_unique(array_merge($idsPivot, $extra)));
    }

    /**
     * FDB → FIB, FDL → FIL, CDB → CIB. Null si no es un tipo directo de 3 letras.
     */
    public static function abreviaturaHermanoIndirecto(string $abreviatura): ?string
    {
        $abreviatura = strtoupper(trim($abreviatura));
        if (strlen($abreviatura) !== 3 || $abreviatura[1] !== 'D') {
            return null;
        }

        return $abreviatura[0].'I'.$abreviatura[2];
    }

    /**
     * @return list<string>
     */
    private static function codigosEnFormula(string $formula): array
    {
        if (preg_match_all('/con\s*\(\s*([0-9]+)\s*\)/i', $formula, $m) < 1) {
            return [];
        }

        return array_values(array_unique(array_map('strval', $m[1] ?? [])));
    }

    /**
     * Catálogo completo, sin filtrar por tipo de comprobante.
     * Lo usa Configuración general para elegir el concepto del débito por cheque rechazado.
     *
     * @return Collection<int, Concepto_Ivacompra>
     */
    public static function listarCatalogo(?string $consulta = null): Collection
    {
        $query = Concepto_Ivacompra::query();
        $texto = trim((string) $consulta);
        if ($texto !== '') {
            $query->where(function ($q) use ($texto) {
                $q->where('codigo', 'like', '%'.$texto.'%')
                    ->orWhere('nombre', 'like', '%'.$texto.'%');
            });
        }

        return $query
            ->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))
            ->orderBy('nombre')
            ->limit(80)
            ->get();
    }

    public static function resolverCatalogo(string $valor): ?Concepto_Ivacompra
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        $porCodigo = Concepto_Ivacompra::query()->where('codigo', $valor)->orderBy('id')->first();
        if ($porCodigo) {
            return $porCodigo;
        }

        if (ctype_digit($valor)) {
            return Concepto_Ivacompra::query()->find((int) $valor);
        }

        return null;
    }
}

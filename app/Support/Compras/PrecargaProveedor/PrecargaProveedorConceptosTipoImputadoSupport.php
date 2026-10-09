<?php

namespace App\Support\Compras\PrecargaProveedor;

use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Models\Compras\Tipotransaccion_Compra_Concepto_Ivacompra;
use App\Support\Compras\ConceptoIvacompraFormulaSupport;
use Illuminate\Support\Collection;

/**
 * Conceptos de una factura que no pertenecen al tipo que se imputa (FGA → FNS):
 * se reubican en el concepto del tipo con la misma clase y la misma alícuota.
 *
 * Las facturas prorrateadas (FPB/CPS/…) usan la unión de conceptos de los centros
 * de costo de la OC y no tienen conceptos propios: quedan fuera de esta regla.
 */
final class PrecargaProveedorConceptosTipoImputadoSupport
{
    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return array{lineas: list<array<string, mixed>>, avisos: list<string>, reubico: bool, revisar: bool}
     */
    public static function reubicar(array $lineas, int $tipotransaccionCompraId, string $abreviaturaTipo): array
    {
        $vacio = ['lineas' => $lineas, 'avisos' => [], 'reubico' => false, 'revisar' => false];
        if ($lineas === [] || $tipotransaccionCompraId <= 0) {
            return $vacio;
        }

        $destino = self::conceptosDelTipo($tipotransaccionCompraId, $abreviaturaTipo);
        if ($destino->isEmpty()) {
            return $vacio;
        }

        $idsOrigen = [];
        foreach ($lineas as $linea) {
            $id = (int) ($linea['concepto_ivacompra_id'] ?? 0);
            if ($id > 0 && ! $destino->has($id)) {
                $idsOrigen[$id] = $id;
            }
        }
        if ($idsOrigen === []) {
            return $vacio;
        }

        $origen = Concepto_Ivacompra::query()
            ->with('impuestos')
            ->whereIn('id', array_values($idsOrigen))
            ->get()
            ->keyBy('id');

        ConceptoIvacompraFormulaSupport::inferirTiposYTasasEnColeccion(
            $destino->concat($origen)->keyBy('id')
        );

        $avisos = [];
        $reubico = false;
        $revisar = false;
        $abrev = strtoupper(trim($abreviaturaTipo));

        foreach ($lineas as &$linea) {
            $id = (int) ($linea['concepto_ivacompra_id'] ?? 0);
            if ($id <= 0 || $destino->has($id)) {
                continue;
            }
            $concepto = $origen->get($id);
            if (! $concepto instanceof Concepto_Ivacompra) {
                continue;
            }

            $candidatos = self::candidatos($destino, $concepto);
            if ($candidatos->count() === 1) {
                /** @var Concepto_Ivacompra $elegido */
                $elegido = $candidatos->first();
                $linea['concepto_ivacompra_id'] = (int) $elegido->id;
                $linea['codigo_concepto_anita'] = (string) $elegido->codigo;
                $reubico = true;
                $avisos[] = 'Concepto '.(string) $concepto->codigo.' '.$concepto->nombre
                    .' no está en '.$abrev.'. Se imputó '.(string) $elegido->codigo.' '.$elegido->nombre.'.';

                continue;
            }

            $revisar = true;
            $avisos[] = $candidatos->isEmpty()
                ? 'Concepto '.(string) $concepto->codigo.' '.$concepto->nombre
                    .' no está en '.$abrev.' y no hay uno equivalente (misma clase y alícuota).'
                : 'Concepto '.(string) $concepto->codigo.' '.$concepto->nombre
                    .' no está en '.$abrev.' y hay más de un equivalente. Quedó sin reubicar.';
        }
        unset($linea);

        return [
            'lineas' => $lineas,
            'avisos' => $avisos,
            'reubico' => $reubico,
            'revisar' => $revisar,
        ];
    }

    /**
     * Concepto de origen → concepto equivalente del tipo (solo los que tienen uno único).
     *
     * @param  list<int>  $conceptoIds
     * @return array<int, int>
     */
    public static function equivalencias(array $conceptoIds, int $tipotransaccionCompraId, ?string $abreviaturaTipo = null): array
    {
        $abrev = $abreviaturaTipo ?? self::abreviaturaTipo($tipotransaccionCompraId);
        $lineas = [];
        foreach (array_unique(array_map('intval', $conceptoIds)) as $id) {
            if ($id > 0) {
                $lineas[] = ['concepto_ivacompra_id' => $id, 'origen' => $id];
            }
        }

        $res = self::reubicar($lineas, $tipotransaccionCompraId, $abrev);
        $out = [];
        foreach ($res['lineas'] as $linea) {
            $origen = (int) $linea['origen'];
            $nuevo = (int) $linea['concepto_ivacompra_id'];
            if ($nuevo !== $origen) {
                $out[$origen] = $nuevo;
            }
        }

        return $out;
    }

    /**
     * Conceptos de IVA (tipoconcepto I) que no están en el tipo. Vacío si el tipo no tiene
     * conceptos cargados o es prorrateado.
     *
     * @param  list<int>  $conceptoIds
     * @return Collection<int, Concepto_Ivacompra>
     */
    public static function ivaFueraDelTipo(array $conceptoIds, int $tipotransaccionCompraId, ?string $abreviaturaTipo = null): Collection
    {
        $abrev = $abreviaturaTipo ?? self::abreviaturaTipo($tipotransaccionCompraId);
        $idsDelTipo = self::idsDelTipo($tipotransaccionCompraId, $abrev);
        if ($idsDelTipo === []) {
            return collect();
        }

        $fuera = array_values(array_diff(
            array_unique(array_filter(array_map('intval', $conceptoIds))),
            $idsDelTipo
        ));
        if ($fuera === []) {
            return collect();
        }

        return Concepto_Ivacompra::query()
            ->whereIn('id', $fuera)
            ->get()
            ->filter(static fn (Concepto_Ivacompra $c): bool => strtoupper(trim((string) ($c->tipoconcepto ?? ''))) === 'I')
            ->values();
    }

    /**
     * @return list<int>
     */
    public static function idsDelTipo(int $tipotransaccionCompraId, ?string $abreviaturaTipo = null): array
    {
        if ($tipotransaccionCompraId <= 0) {
            return [];
        }
        $abrev = $abreviaturaTipo ?? self::abreviaturaTipo($tipotransaccionCompraId);
        if (PrecargaProveedorProrrateoMultiCcSupport::esTipoProrrateado($abrev)) {
            return [];
        }

        return Tipotransaccion_Compra_Concepto_Ivacompra::query()
            ->where('tipotransaccion_compra_id', $tipotransaccionCompraId)
            ->pluck('concepto_ivacompra_id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public static function abreviaturaTipo(int $tipotransaccionCompraId): string
    {
        if ($tipotransaccionCompraId <= 0) {
            return '';
        }

        return strtoupper(trim((string) (Tipotransaccion_Compra::query()
            ->whereKey($tipotransaccionCompraId)
            ->value('abreviatura') ?? '')));
    }

    /**
     * @return Collection<int, Concepto_Ivacompra>
     */
    private static function conceptosDelTipo(int $tipotransaccionCompraId, string $abreviaturaTipo): Collection
    {
        $ids = self::idsDelTipo($tipotransaccionCompraId, $abreviaturaTipo);
        if ($ids === []) {
            return collect();
        }

        return Concepto_Ivacompra::query()
            ->with('impuestos')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, Concepto_Ivacompra>  $destino
     * @return Collection<int, Concepto_Ivacompra>
     */
    private static function candidatos(Collection $destino, Concepto_Ivacompra $origen): Collection
    {
        $tipo = strtoupper(trim((string) ($origen->tipoconcepto ?? '')));
        $tasa = self::tasaKey(ConceptoIvacompraFormulaSupport::tasaEfectiva($origen));

        $filtrados = $destino->filter(function (Concepto_Ivacompra $concepto) use ($tipo, $tasa): bool {
            return strtoupper(trim((string) ($concepto->tipoconcepto ?? ''))) === $tipo
                && self::tasaKey(ConceptoIvacompraFormulaSupport::tasaEfectiva($concepto)) === $tasa;
        })->values();

        if ($filtrados->count() <= 1) {
            return $filtrados;
        }

        $sinDescuento = $filtrados->filter(function (Concepto_Ivacompra $concepto): bool {
            return ! str_starts_with(mb_strtolower((string) $concepto->nombre), 'descuento');
        })->values();

        return $sinDescuento->count() === 1 ? $sinDescuento : $filtrados;
    }

    private static function tasaKey(float $tasa): string
    {
        return number_format(round($tasa, 3), 3, '.', '');
    }
}

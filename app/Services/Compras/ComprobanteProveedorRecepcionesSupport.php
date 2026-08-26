<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor_Recepcion;
use App\Models\Stock\Recepcion_Proveedor;
use App\Services\Stock\RecepcionProveedorAsientoService;
use App\Services\Stock\RecepcionProveedorImportarDesdeAnitaService;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\ComprobanteProveedorImporteComparacionComSupport;
use App\Support\Stock\RecepcionProveedorConversionSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ComprobanteProveedorRecepcionesSupport
{
    public function __construct(
        private RecepcionProveedorAsientoService $recepcionAsientoService,
        private RecepcionProveedorImportarDesdeAnitaService $importarDesdeAnitaService,
    ) {}

    /**
     * Recepciones CONFIRMADAS de la OC con provisión contable y sin factura contabilizada previa.
     * Completa desde Anita las COM de la OC que aún no están en ERP (o están huérfanas),
     * salvo que $sincronizarAnita sea false (apertura del formulario: no bloquear la UI).
     *
     * @return Collection<int, Recepcion_Proveedor>
     */
    public function listarDisponibles(int $ordencompraId, ?int $comprobanteId = null, bool $sincronizarAnita = true): Collection
    {
        if ($sincronizarAnita) {
            $this->asegurarRecepcionesDesdeAnita($ordencompraId);
        }

        $yaFacturadas = $this->recepcionIdsFacturadas($comprobanteId);

        return Recepcion_Proveedor::query()
            ->with([
                'ordencompras',
                'recepcion_proveedor_articulos.articulos',
                'recepcion_proveedor_articulos.unidadesmedida',
            ])
            ->where('ordencompra_id', $ordencompraId)
            ->where('tipo', Recepcion_Proveedor::TIPO_RECEPCION)
            ->where('estado', Recepcion_Proveedor::ESTADO_CONFIRMADA)
            ->tap(fn (Builder $q) => self::aplicarCriterioAsignable($q))
            ->when($yaFacturadas !== [], fn ($q) => $q->whereNotIn('id', $yaFacturadas))
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();
    }

    /**
     * COM sin facturar del proveedor en el sector legajo (todas las OC del legajo).
     *
     * @return Collection<int, Recepcion_Proveedor>
     */
    public function listarSinFacturarEnLegajo(
        int $proveedorId,
        int $empresaId,
        ?int $sectorLegajocompraId,
        ?int $excluirComprobanteId = null,
    ): Collection {
        $yaFacturadas = $this->recepcionIdsFacturadas($excluirComprobanteId);

        return Recepcion_Proveedor::query()
            ->with([
                'ordencompras',
                'recepcion_proveedor_articulos.articulos',
                'recepcion_proveedor_articulos.unidadesmedida',
            ])
            ->where('proveedor_id', $proveedorId)
            ->where('empresa_id', $empresaId)
            ->where('tipo', Recepcion_Proveedor::TIPO_RECEPCION)
            ->where('estado', Recepcion_Proveedor::ESTADO_CONFIRMADA)
            ->tap(fn (Builder $q) => self::aplicarCriterioAsignable($q))
            ->whereHas('ordencompras', function ($query) use ($sectorLegajocompraId) {
                if ($sectorLegajocompraId) {
                    $query->where('sector_legajocompra_id', $sectorLegajocompraId);
                }
            })
            ->when($yaFacturadas !== [], fn ($q) => $q->whereNotIn('id', $yaFacturadas))
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, Recepcion_Proveedor>  $recepciones
     *
     * @return Collection<int, Recepcion_Proveedor>
     */
    public function enriquecerConImporteProvision(Collection $recepciones): Collection
    {
        $recepciones->loadMissing(['monedas']);

        $totalesPorAsiento = $this->totalesDebePorAsientoId($recepciones);

        return $recepciones->map(function (Recepcion_Proveedor $recepcion) use ($totalesPorAsiento) {
            $asientoId = (int) ($recepcion->asiento_id ?? 0);
            if ($asientoId > 0 && array_key_exists($asientoId, $totalesPorAsiento)) {
                $me = round((float) $totalesPorAsiento[$asientoId], 2);
            } else {
                $me = $this->importeProvisionCom($recepcion);
            }
            $recepcion->importe_provision_com = $me;
            $recepcion->importe_provision_com_mn = $this->importeProvisionComEnMonedaLocal($recepcion, $me);

            return $recepcion;
        });
    }

    /**
     * Expresa la provisión COM en la moneda de la factura (manda la factura).
     *
     * @param  Collection<int, Recepcion_Proveedor>  $recepciones
     * @return Collection<int, Recepcion_Proveedor>
     */
    public function enriquecerConImporteEnMonedaFactura(
        Collection $recepciones,
        int $monedaFacturaId,
        float $cotizacionFactura,
        mixed $fechaFactura = null,
    ): Collection {
        $necesitaProvision = $recepciones->contains(function (Recepcion_Proveedor $r) {
            return ! isset($r->importe_provision_com);
        });
        if ($necesitaProvision) {
            $recepciones = $this->enriquecerConImporteProvision($recepciones);
        }

        return $recepciones->map(function (Recepcion_Proveedor $recepcion) use (
            $monedaFacturaId,
            $cotizacionFactura,
            $fechaFactura,
        ) {
            $me = (float) ($recepcion->importe_provision_com ?? 0);
            $recepcion->importe_provision_com_factura = ComprobanteProveedorImporteComparacionComSupport::desdeRecepcionAFacturaTolerante(
                $me,
                (int) ($recepcion->moneda_id ?: 1),
                (float) ($recepcion->cotizacion ?: 0),
                $monedaFacturaId,
                $cotizacionFactura,
                $recepcion->fecha ?? null,
                $fechaFactura,
            );

            return $recepcion;
        });
    }

    /**
     * Carga artículos de cada COM para mostrar en la carga de factura.
     *
     * @param  Collection<int, Recepcion_Proveedor>  $recepciones
     * @return Collection<int, Recepcion_Proveedor>
     */
    public function enriquecerConArticulos(Collection $recepciones): Collection
    {
        $recepciones->loadMissing([
            'recepcion_proveedor_articulos.articulos',
            'recepcion_proveedor_articulos.unidadesmedida',
        ]);

        return $recepciones;
    }

    /**
     * Provisión COM en moneda de la recepción (p. ej. USD 623 del DEBE del asiento).
     */
    public function importeProvisionCom(Recepcion_Proveedor $recepcion): float
    {
        $asientoId = (int) ($recepcion->asiento_id ?? 0);
        if ($asientoId > 0) {
            $totales = $this->totalesDebePorAsientoId(collect([$recepcion]));
            if (array_key_exists($asientoId, $totales)) {
                return round((float) $totales[$asientoId], 2);
            }
        }

        $desdeLineas = $this->importeProvisionDesdeLineas($recepcion);
        if ($desdeLineas > 0) {
            return $desdeLineas;
        }

        try {
            $preview = $this->recepcionAsientoService->previewAsientoContable($recepcion);

            return round((float) ($preview['total_debe'] ?? 0), 2);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function importeProvisionDesdeLineas(Recepcion_Proveedor $recepcion): float
    {
        $recepcion->loadMissing('recepcion_proveedor_articulos');
        if ($recepcion->recepcion_proveedor_articulos->isEmpty()) {
            return 0.0;
        }

        $monedaRecepcionId = (int) ($recepcion->moneda_id ?: 1);
        $total = 0.0;
        foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
            $total += RecepcionProveedorConversionSupport::importeLineaEnMonedaReferencia(
                $monedaRecepcionId,
                (int) ($linea->moneda_id ?: $monedaRecepcionId),
                (float) $linea->cantidad,
                (float) $linea->precio,
                (float) ($linea->descuento ?? 0),
                0,
                (float) ($linea->cotizacion ?: 1),
            );
        }

        return round($total + (float) ($recepcion->impuesto_interno ?? 0), 2);
    }

    /**
     * Provisión COM en moneda local: si la recepción es ME, ME × cotización.
     */
    public function importeProvisionComEnMonedaLocal(Recepcion_Proveedor $recepcion, ?float $importeMe = null): float
    {
        $me = $importeMe !== null ? round($importeMe, 2) : $this->importeProvisionCom($recepcion);

        return ComprobanteProveedorImporteComparacionComSupport::aMonedaLocal(
            $me,
            (int) ($recepcion->moneda_id ?: 1),
            (float) ($recepcion->cotizacion ?: 0),
            $recepcion->fecha ?? null,
            'la recepción COM '.($recepcion->numerorecepcion ?? $recepcion->id),
        );
    }

    /**
     * @param  list<int|string>  $recepcionIds
     * @param  array{proveedor_id?: int, empresa_id?: int, sector_legajocompra_id?: int|null}|null  $contextoLegajo
     */
    public function sincronizar(
        int $comprobanteId,
        int $ordencompraId,
        array $recepcionIds,
        ?array $contextoLegajo = null,
    ): void {
        $ids = collect($recepcionIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        Comprobante_Proveedor_Recepcion::query()
            ->where('comprobante_proveedor_id', $comprobanteId)
            ->delete();

        if ($ids === []) {
            return;
        }

        $permitidos = $this->idsRecepcionesPermitidas($comprobanteId, $ordencompraId, $contextoLegajo);

        $orden = 0;
        foreach ($ids as $recepcionId) {
            if (! in_array($recepcionId, $permitidos, true)) {
                throw new RuntimeException(
                    'La recepción #'.$recepcionId.' no está disponible (no confirmada, sin provisión o ya facturada).'
                );
            }

            $orden++;
            Comprobante_Proveedor_Recepcion::query()->create([
                'comprobante_proveedor_id' => $comprobanteId,
                'recepcion_proveedor_id' => $recepcionId,
                'orden' => $orden,
            ]);
        }
    }

    /** @return list<int> */
    public function idsRecepcionesPermitidas(
        int $comprobanteId,
        int $ordencompraId,
        ?array $contextoLegajo = null,
    ): array {
        $disponibles = $this->listarDisponibles($ordencompraId, $comprobanteId)->pluck('id')->all();

        if ($contextoLegajo
            && (int) ($contextoLegajo['proveedor_id'] ?? 0) > 0
            && (int) ($contextoLegajo['empresa_id'] ?? 0) > 0) {
            $legajo = $this->listarSinFacturarEnLegajo(
                (int) $contextoLegajo['proveedor_id'],
                (int) $contextoLegajo['empresa_id'],
                $contextoLegajo['sector_legajocompra_id'] ?? null,
                $comprobanteId,
            )->pluck('id')->all();
            $disponibles = array_values(array_unique(array_merge($disponibles, $legajo)));
        }

        $vinculadosActuales = Comprobante_Proveedor_Recepcion::query()
            ->where('comprobante_proveedor_id', $comprobanteId)
            ->pluck('recepcion_proveedor_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge($disponibles, $vinculadosActuales)));
    }

    /** @return list<int> */
    public function recepcionIdsFacturadas(?int $excluirComprobanteId = null): array
    {
        $query = DB::table('comprobante_proveedor_recepcion as cpr')
            ->join('comprobante_proveedor as cp', 'cp.id', '=', 'cpr.comprobante_proveedor_id')
            ->where('cp.estado', ComprobanteProveedorEstados::CONTABILIZADO);

        if ($excluirComprobanteId) {
            $query->where('cp.id', '!=', $excluirComprobanteId);
        }

        return $query->pluck('cpr.recepcion_proveedor_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * COM nativas con asiento, o históricas importadas de Anita (provisión ya en Anita).
     */
    public static function aplicarCriterioAsignable(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNotNull('asiento_id')
                ->orWhere('origen_carga', 'ANITA_IMPORT');
        });
    }

    /**
     * @return array{claves: int, importadas: int, vinculadas: int, omitidas: int, errores: list<string>}
     */
    public function sincronizarDesdeAnita(int $ordencompraId): array
    {
        $vacio = [
            'claves' => 0,
            'importadas' => 0,
            'vinculadas' => 0,
            'omitidas' => 0,
            'errores' => [],
        ];
        if ($ordencompraId <= 0) {
            return $vacio;
        }

        try {
            return $this->importarDesdeAnitaService->asegurarPorOrdencompraId($ordencompraId);
        } catch (\Throwable $e) {
            Log::warning('ComprobanteProveedorRecepciones: fallback Anita por OC', [
                'ordencompra_id' => $ordencompraId,
                'mensaje' => $e->getMessage(),
            ]);
            $vacio['errores'][] = $e->getMessage();

            return $vacio;
        }
    }

    private function asegurarRecepcionesDesdeAnita(int $ordencompraId): void
    {
        $this->sincronizarDesdeAnita($ordencompraId);
    }

    /**
     * @param  Collection<int, Recepcion_Proveedor>  $recepciones
     * @return array<int, float>
     */
    private function totalesDebePorAsientoId(Collection $recepciones): array
    {
        $asientoIds = $recepciones
            ->map(fn (Recepcion_Proveedor $r) => (int) ($r->asiento_id ?? 0))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        if ($asientoIds === []) {
            return [];
        }

        return DB::table('asiento_movimiento')
            ->selectRaw('asiento_id, ROUND(SUM(CASE WHEN monto > 0 THEN monto ELSE 0 END), 2) as total_debe')
            ->whereIn('asiento_id', $asientoIds)
            ->groupBy('asiento_id')
            ->pluck('total_debe', 'asiento_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }
}

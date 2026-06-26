<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor_Recepcion;
use App\Models\Stock\Recepcion_Proveedor;
use App\Services\Stock\RecepcionProveedorAsientoService;
use App\Support\Compras\ComprobanteProveedorEstados;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ComprobanteProveedorRecepcionesSupport
{
    public function __construct(
        private RecepcionProveedorAsientoService $recepcionAsientoService,
    ) {}

    /**
     * Recepciones CONFIRMADAS de la OC con provisión contable y sin factura contabilizada previa.
     *
     * @return Collection<int, Recepcion_Proveedor>
     */
    public function listarDisponibles(int $ordencompraId, ?int $comprobanteId = null): Collection
    {
        $yaFacturadas = $this->recepcionIdsFacturadas($comprobanteId);

        return Recepcion_Proveedor::query()
            ->with(['ordencompras'])
            ->where('ordencompra_id', $ordencompraId)
            ->where('tipo', Recepcion_Proveedor::TIPO_RECEPCION)
            ->where('estado', Recepcion_Proveedor::ESTADO_CONFIRMADA)
            ->whereNotNull('asiento_id')
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
            ->with(['ordencompras'])
            ->where('proveedor_id', $proveedorId)
            ->where('empresa_id', $empresaId)
            ->where('tipo', Recepcion_Proveedor::TIPO_RECEPCION)
            ->where('estado', Recepcion_Proveedor::ESTADO_CONFIRMADA)
            ->whereNotNull('asiento_id')
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
        return $recepciones->map(function (Recepcion_Proveedor $recepcion) {
            $recepcion->importe_provision_com = $this->importeProvisionCom($recepcion);

            return $recepcion;
        });
    }

    public function importeProvisionCom(Recepcion_Proveedor $recepcion): float
    {
        try {
            $preview = $this->recepcionAsientoService->previewAsientoContable($recepcion);

            return round((float) ($preview['total_debe'] ?? 0), 2);
        } catch (\Throwable) {
            return 0.0;
        }
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
            ->where('cp.estado', ComprobanteProveedorEstados::CONTABILIZADO)
            ->whereNull('cp.deleted_at');

        if ($excluirComprobanteId) {
            $query->where('cp.id', '!=', $excluirComprobanteId);
        }

        return $query->pluck('cpr.recepcion_proveedor_id')->map(fn ($id) => (int) $id)->all();
    }
}

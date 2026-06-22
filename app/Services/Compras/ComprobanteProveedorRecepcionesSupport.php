<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor_Recepcion;
use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Compras\ComprobanteProveedorEstados;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ComprobanteProveedorRecepcionesSupport
{
    /**
     * Recepciones CONFIRMADAS de la OC con provisión contable y sin factura contabilizada previa.
     *
     * @return Collection<int, Recepcion_Proveedor>
     */
    public function listarDisponibles(int $ordencompraId, ?int $comprobanteId = null): Collection
    {
        $yaFacturadas = $this->recepcionIdsFacturadas($comprobanteId);

        return Recepcion_Proveedor::query()
            ->where('ordencompra_id', $ordencompraId)
            ->where('estado', Recepcion_Proveedor::ESTADO_CONFIRMADA)
            ->whereNotNull('asiento_id')
            ->when($yaFacturadas !== [], fn ($q) => $q->whereNotIn('id', $yaFacturadas))
            ->orderBy('id')
            ->get();
    }

    /** @param list<int|string> $recepcionIds */
    public function sincronizar(int $comprobanteId, int $ordencompraId, array $recepcionIds): void
    {
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

        $disponibles = $this->listarDisponibles($ordencompraId, $comprobanteId)->pluck('id')->all();
        $vinculadosActuales = Comprobante_Proveedor_Recepcion::query()
            ->where('comprobante_proveedor_id', $comprobanteId)
            ->pluck('recepcion_proveedor_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $permitidos = array_values(array_unique(array_merge($disponibles, $vinculadosActuales)));

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

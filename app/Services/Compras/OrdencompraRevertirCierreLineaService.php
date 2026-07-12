<?php

namespace App\Services\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Articulo;
use App\Models\Stock\Recepcion_Proveedor;
use App\Repositories\Compras\OrdencompraRepositoryInterface;
use App\Support\Compras\OrdencompraLineaEstados;
use App\Support\Stock\RecepcionProveedorOcPendienteSupport;
use Auth;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reabre líneas OC cerradas por error en recepción (fl_cerrar_linea_oc) y recalcula cabecera.
 */
class OrdencompraRevertirCierreLineaService
{
    public function __construct(
        private readonly OrdencompraRepositoryInterface $ordencompraRepository,
        private readonly OrdencompraRecepcionCumplimientoService $ordencompraRecepcionCumplimientoService,
    ) {
    }

    public function puedeRevertir(int $ordencompraId): bool
    {
        return $this->lineasCerradasConSaldoPendiente($ordencompraId)->isNotEmpty();
    }

    /**
     * @return array{
     *     puede_revertir: bool,
     *     lineas: list<array{id: int, articulo_id: int, cantidad_pedida: float, cantidad_recibida: float, cantidad_pendiente: float}>,
     *     cantidad_pendiente_total: float
     * }
     */
    public function resumen(int $ordencompraId): array
    {
        $lineas = $this->lineasCerradasConSaldoPendiente($ordencompraId);
        $detalle = [];
        $pendienteTotal = 0.0;

        foreach ($lineas as $linea) {
            $pendiente = (float) ($linea->cantidad_pendiente_calc ?? 0);
            $pendienteTotal += $pendiente;
            $detalle[] = [
                'id' => (int) $linea->id,
                'articulo_id' => (int) $linea->articulo_id,
                'cantidad_pedida' => (float) $linea->cantidad,
                'cantidad_recibida' => (float) ($linea->cantidad_recibida_calc ?? 0),
                'cantidad_pendiente' => $pendiente,
            ];
        }

        return [
            'puede_revertir' => $lineas->isNotEmpty(),
            'lineas' => $detalle,
            'cantidad_pendiente_total' => $pendienteTotal,
        ];
    }

    /**
     * @return array{mensaje: string, errores?: string, lineas_reabiertas?: int, cantidad_pendiente?: float, estado_nuevo?: string}
     */
    public function revertir(int $ordencompraId, ?int $usuarioId = null, ?string $observacion = null): array
    {
        $lineas = $this->lineasCerradasConSaldoPendiente($ordencompraId);
        if ($lineas->isEmpty()) {
            return [
                'mensaje' => 'error',
                'errores' => 'No hay líneas cerradas con saldo pendiente de recepción para reabrir.',
            ];
        }

        $uid = $usuarioId ?? (int) (Auth::id() ?? 0);
        $obs = $observacion !== null && $observacion !== ''
            ? $observacion
            : 'Reapertura de líneas cerradas por error en recepción';

        $lineaIds = $lineas->pluck('id')->map(fn ($id) => (int) $id)->all();

        DB::transaction(function () use ($ordencompraId, $lineaIds, $uid, $obs): void {
            Ordencompra_Articulo::query()
                ->whereIn('id', $lineaIds)
                ->update(['estado_linea_oc' => OrdencompraLineaEstados::ACTIVA]);

            DB::table('recepcion_proveedor_articulo as rpa')
                ->join('recepcion_proveedor as rp', 'rp.id', '=', 'rpa.recepcion_proveedor_id')
                ->where('rp.ordencompra_id', $ordencompraId)
                ->where('rp.estado', Recepcion_Proveedor::ESTADO_CONFIRMADA)
                ->whereIn('rpa.ordencompra_articulo_id', $lineaIds)
                ->where('rpa.fl_cerrar_linea_oc', true)
                ->update(['rpa.fl_cerrar_linea_oc' => false]);

            $oc = $this->ordencompraRepository->find($ordencompraId);
            $this->ordencompraRecepcionCumplimientoService->recalcularEstadoTrasReaperturaLineas($oc, $uid, $obs);
        });

        $oc = $this->ordencompraRepository->find($ordencompraId);
        $saldo = RecepcionProveedorOcPendienteSupport::calcularSaldoRecepcionEstricto($ordencompraId);

        return [
            'mensaje' => 'ok',
            'lineas_reabiertas' => count($lineaIds),
            'cantidad_pendiente' => (float) ($saldo['cantidad_pendiente'] ?? 0),
            'estado_nuevo' => (string) ($oc->estadoordencompra ?? ''),
        ];
    }

    /** @return Collection<int, Ordencompra_Articulo&object{cantidad_recibida_calc?: float, cantidad_pendiente_calc?: float}> */
    private function lineasCerradasConSaldoPendiente(int $ordencompraId): Collection
    {
        if ($ordencompraId <= 0) {
            return collect();
        }

        $oc = Ordencompra::query()->find($ordencompraId);
        if ($oc === null) {
            return collect();
        }

        $recibidos = RecepcionProveedorOcPendienteSupport::cantidadesRecibidasPorLineaOc($ordencompraId);

        return Ordencompra_Articulo::query()
            ->where('ordencompra_id', $ordencompraId)
            ->where('estado_linea_oc', OrdencompraLineaEstados::CERRADA)
            ->get()
            ->map(function (Ordencompra_Articulo $linea) use ($recibidos) {
                $recibida = (float) ($recibidos[(int) $linea->id] ?? 0);
                $pendiente = RecepcionProveedorOcPendienteSupport::saldoPendienteLineaEstricto(
                    (float) $linea->cantidad,
                    $recibida
                );
                $linea->cantidad_recibida_calc = $recibida;
                $linea->cantidad_pendiente_calc = $pendiente;

                return $linea;
            })
            ->filter(fn (Ordencompra_Articulo $linea) => (float) ($linea->cantidad_pendiente_calc ?? 0) > 0.000001)
            ->values();
    }
}

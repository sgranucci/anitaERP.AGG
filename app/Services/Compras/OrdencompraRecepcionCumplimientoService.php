<?php

namespace App\Services\Compras;

use App\Models\Compras\Ordencompra;
use App\Repositories\Compras\OrdencompraRepositoryInterface;
use App\Repositories\Compras\Ordencompra_EstadoRepositoryInterface;
use App\Support\Compras\OrdencompraEstados;
use App\Support\Stock\RecepcionProveedorOcPendienteSupport;
use Auth;
use Illuminate\Support\Carbon;

/**
 * Sincroniza estadoordencompra según saldo recepcionado (COM neto de devoluciones DEP confirmadas).
 */
class OrdencompraRecepcionCumplimientoService
{
    public function __construct(
        private readonly OrdencompraRepositoryInterface $ordencompraRepository,
        private readonly Ordencompra_EstadoRepositoryInterface $ordencompraEstadoRepository,
    ) {
    }

    public function sincronizarEstadoCabecera(int $ordencompraId, ?int $usuarioId = null, ?string $observacion = null): bool
    {
        $oc = Ordencompra::query()->find($ordencompraId);

        return $oc !== null && $this->sincronizarEstadoCabeceraOc($oc, $usuarioId, $observacion);
    }

    public function sincronizarEstadoCabeceraOc(Ordencompra $oc, ?int $usuarioId = null, ?string $observacion = null): bool
    {
        $estadoActual = (string) ($oc->estadoordencompra ?? '');

        if (in_array($estadoActual, [OrdencompraEstados::CERRADA, OrdencompraEstados::SUSPENDIDA], true)) {
            return false;
        }

        $tieneSaldoEstricto = RecepcionProveedorOcPendienteSupport::tieneSaldoPendienteEstricto((int) $oc->id);
        $tieneSaldoTolerancia = RecepcionProveedorOcPendienteSupport::tieneSaldoPendiente((int) $oc->id);

        if ($tieneSaldoEstricto || $tieneSaldoTolerancia) {
            if (in_array($estadoActual, [OrdencompraEstados::CUMPLIDA, OrdencompraEstados::CERRADA], true)) {
                return $this->revertirCierreCabeceraSiCorresponde(
                    $oc,
                    $usuarioId,
                    'Recepción anulada o ajustada: la OC vuelve a tener saldo pendiente'
                );
            }

            return false;
        }

        if ($estadoActual === OrdencompraEstados::CUMPLIDA) {
            return false;
        }

        $uid = $usuarioId ?? (int) (Auth::id() ?? 0);
        $obs = $observacion ?? 'Recepción completa de la orden de compra';

        $this->ordencompraRepository->update(
            ['estadoordencompra' => OrdencompraEstados::CUMPLIDA],
            (int) $oc->id
        );

        if ($uid > 0) {
            $this->ordencompraEstadoRepository->creaEstado(
                (int) $oc->id,
                Carbon::now()->toDateTimeString(),
                OrdencompraEstados::CUMPLIDA,
                $uid,
                $obs
            );
        }

        return true;
    }

    public function recalcularEstadoTrasReaperturaLineas(
        Ordencompra $oc,
        ?int $usuarioId = null,
        ?string $observacion = null
    ): bool {
        if (! RecepcionProveedorOcPendienteSupport::tieneSaldoPendienteEstricto((int) $oc->id)) {
            return false;
        }

        return $this->revertirCierreCabeceraSiCorresponde(
            $oc,
            $usuarioId,
            $observacion ?? 'Reapertura de líneas: la OC vuelve a tener saldo pendiente por recepciones'
        );
    }

    private function revertirCierreCabeceraSiCorresponde(Ordencompra $oc, ?int $usuarioId, string $observacion): bool
    {
        $estadoActual = (string) ($oc->estadoordencompra ?? '');
        if (! in_array($estadoActual, [OrdencompraEstados::CUMPLIDA, OrdencompraEstados::CERRADA], true)) {
            return false;
        }

        $uid = $usuarioId ?? (int) (Auth::id() ?? 0);
        $nuevoEstado = OrdencompraEstados::APROBADA;

        $this->ordencompraRepository->update(
            ['estadoordencompra' => $nuevoEstado],
            (int) $oc->id
        );

        if ($uid > 0) {
            $this->ordencompraEstadoRepository->creaEstado(
                (int) $oc->id,
                Carbon::now()->toDateTimeString(),
                $nuevoEstado,
                $uid,
                $observacion
            );
        }

        return true;
    }
}

<?php

namespace App\Services\Solicitudpago;

use App\Models\Caja\Caja_Movimiento;
use App\Repositories\Solicitudpago\SolicitudpagoRepositoryInterface;
use App\Support\Solicitudpago\SolicitudpagoEstados;
use Illuminate\Support\Facades\Log;

/**
 * Al vincular un IE de caja con una SP AUTORIZADA, la marca PAGADA (Anita a-movim).
 */
class SolicitudpagoPagoDesdeCajaService
{
    public function __construct(
        private SolicitudpagoRepositoryInterface $repository,
    ) {
    }

    public function sincronizarDesdeMovimiento(Caja_Movimiento $movimiento): void
    {
        $spId = (int) ($movimiento->solicitudpago_id ?? 0);
        if ($spId <= 0) {
            return;
        }

        try {
            $sp = $this->repository->findOrFail($spId);
            if ($sp->estado === SolicitudpagoEstados::PAGADA) {
                return;
            }
            if ($sp->estado !== SolicitudpagoEstados::AUTORIZADA) {
                Log::info('solicitudpago.pago_caja_omitido', [
                    'solicitudpago_id' => $spId,
                    'estado' => $sp->estado,
                    'caja_movimiento_id' => $movimiento->id,
                ]);

                return;
            }
            $this->repository->cambiarEstado($spId, SolicitudpagoEstados::PAGADA, 'Pagada desde IE '.$movimiento->id);
        } catch (\Throwable $e) {
            Log::error('solicitudpago.pago_caja', [
                'solicitudpago_id' => $spId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

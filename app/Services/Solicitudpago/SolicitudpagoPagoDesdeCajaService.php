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
            $this->repository->cambiarEstado(
                $spId,
                SolicitudpagoEstados::PAGADA,
                self::leyendaPagadaDesdeMovimiento($movimiento)
            );
        } catch (\Throwable $e) {
            Log::error('solicitudpago.pago_caja', [
                'solicitudpago_id' => $spId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Tras anular/revertir el IE de pago: vuelve la SP a AUTORIZADA.
     */
    public function revertirAAutorizada(Caja_Movimiento $movimiento, string $leyenda): void
    {
        $spId = (int) ($movimiento->solicitudpago_id ?? 0);
        if ($spId <= 0) {
            return;
        }

        $sp = $this->repository->findOrFail($spId);
        if ($sp->estado !== SolicitudpagoEstados::PAGADA) {
            Log::info('solicitudpago.reverso_caja_omitido', [
                'solicitudpago_id' => $spId,
                'estado' => $sp->estado,
                'caja_movimiento_id' => $movimiento->id,
            ]);

            return;
        }

        $this->repository->cambiarEstado(
            $spId,
            SolicitudpagoEstados::AUTORIZADA,
            $leyenda !== '' ? $leyenda : 'Reabre pago (IE '.$movimiento->id.')'
        );
    }

    private static function leyendaPagadaDesdeMovimiento(Caja_Movimiento $movimiento): string
    {
        $movimiento->loadMissing(['tipotransaccioncajas']);
        $abrev = strtoupper(trim((string) ($movimiento->tipotransaccioncajas->abreviatura ?? 'OPP')));
        if ($abrev === '') {
            $abrev = 'OPP';
        }
        $nro = (string) ($movimiento->numerotransaccion ?? '');
        $id = (int) $movimiento->id;

        if ($nro !== '') {
            return 'Pagada '.$abrev.' '.$nro.' (IE id '.$id.')';
        }

        return 'Pagada desde IE '.$id;
    }
}

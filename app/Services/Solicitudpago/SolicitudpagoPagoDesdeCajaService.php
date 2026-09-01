<?php

namespace App\Services\Solicitudpago;

use App\Models\Caja\Caja_Movimiento;
use App\Repositories\Solicitudpago\SolicitudpagoRepositoryInterface;
use App\Services\Sueldos\LiquidacionSolicitudpagoService;
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
            $sp = $this->repository->findOrFail($spId);
            app(LiquidacionSolicitudpagoService::class)->marcarPagada($sp);
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
        $sp = $this->repository->findOrFail($spId);
        app(LiquidacionSolicitudpagoService::class)->revertirPago($sp);
    }

    /**
     * Si al revertir/anular una OP duplicada queda otra OP vigente de la misma SP,
     * la solicitud vuelve a PAGADA (no debe quedar impaga).
     */
    public function remarcPagadaSiQuedaPagoVigente(Caja_Movimiento $movimiento): void
    {
        $spId = (int) ($movimiento->solicitudpago_id ?? 0);
        if ($spId <= 0) {
            return;
        }

        $vigente = Caja_Movimiento::query()
            ->with(['tipotransaccioncajas'])
            ->where('solicitudpago_id', $spId)
            ->whereNull('caja_movimiento_origen_id')
            ->whereNull('caja_movimiento_revertido_por_id')
            ->where('id', '!=', (int) $movimiento->id)
            ->orderByDesc('id')
            ->first();

        if ($vigente === null) {
            return;
        }

        $sp = $this->repository->findOrFail($spId);
        if ($sp->estado === SolicitudpagoEstados::PAGADA) {
            return;
        }

        $abrev = strtoupper(trim((string) ($vigente->tipotransaccioncajas->abreviatura ?? 'OPP')));
        if ($abrev === '') {
            $abrev = 'OPP';
        }
        $nro = (string) ($vigente->numerotransaccion ?? '');
        $leyenda = $nro !== ''
            ? 'Sigue PAGADA: queda '.$abrev.' '.$nro.' (IE id '.$vigente->id.')'
            : 'Sigue PAGADA: queda IE '.$vigente->id;

        $this->repository->cambiarEstado($spId, SolicitudpagoEstados::PAGADA, $leyenda);
        $sp = $this->repository->findOrFail($spId);
        app(LiquidacionSolicitudpagoService::class)->marcarPagada($sp);

        Log::info('solicitudpago.remarc_pagada_op_vigente', [
            'solicitudpago_id' => $spId,
            'caja_movimiento_revertido_id' => (int) $movimiento->id,
            'caja_movimiento_vigente_id' => (int) $vigente->id,
        ]);
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

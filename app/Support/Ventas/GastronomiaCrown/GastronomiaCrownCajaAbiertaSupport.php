<?php

namespace App\Support\Ventas\GastronomiaCrown;

use App\Models\Caja\CajeroCrown;
use App\Models\Caja\TesoreriaCrownComprobante;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Ventas\GastronomiaCrown\GastroCrownCuenta;
use App\Models\Ventas\MozoGastronomia;
use App\Support\Caja\TesoreriaCrownComprobanteSupport;
use App\Support\Caja\TesoreriaCrownDiaCajeroSupport;
use InvalidArgumentException;

/**
 * Paridad Anita COMAND_verifica_caja_abierta (comandera_view.fc):
 * el mozo/cajero tiene habilitación conforme vigente (cajero sin cierre activo)
 * en la jornada de tesorería de esa fecha.
 *
 * Hotel (cargo a habitación): no se exige. El control era de gastronomía;
 * cobrar hotel no mueve efectivo de caja del mozo.
 */
final class GastronomiaCrownCajaAbiertaSupport
{
    public static function exigirParaCobro(
        MozoGastronomia $mozo,
        string $tipoCobro,
        string $fechaYmd,
        ?int $salaId = null,
    ): void {
        if ($tipoCobro === GastroCrownCuenta::COBRO_HOTEL) {
            return;
        }

        if (self::tieneCajaAbierta($mozo, $fechaYmd, $salaId)) {
            return;
        }

        throw new InvalidArgumentException('No tiene caja abierta en tesorería.');
    }

    public static function tieneCajaAbierta(
        MozoGastronomia $mozo,
        string $fechaYmd,
        ?int $salaId = null,
    ): bool {
        $cajero = CajeroCrown::query()
            ->where('mozo_gastronomia_id', (int) $mozo->id)
            ->orderBy('id')
            ->first();
        if ($cajero === null) {
            return false;
        }

        $jornadaIds = TesoreriaCrownJornada::query()
            ->when($salaId !== null && $salaId > 0, fn ($q) => $q->where('sala_id', $salaId))
            ->when($salaId === null || $salaId <= 0, fn ($q) => $q->where('sala_id', $cajero->sala_id))
            ->whereDate('fecha', $fechaYmd)
            ->pluck('id');
        if ($jornadaIds->isEmpty()) {
            return false;
        }

        foreach ($jornadaIds as $jornadaId) {
            if (TesoreriaCrownDiaCajeroSupport::cierreAbierto((int) $jornadaId, (int) $cajero->id)) {
                continue;
            }
            $hayHab = TesoreriaCrownComprobante::query()
                ->where('jornada_id', $jornadaId)
                ->where('cajero_crown_id', $cajero->id)
                ->where('tipo', TesoreriaCrownComprobanteSupport::HABILITACION)
                ->where('estado', TesoreriaCrownComprobanteSupport::CONFORME)
                ->whereNull('revierte_id')
                ->whereNotExists(function ($q) {
                    $q->selectRaw('1')
                        ->from('tesoreria_crown_comprobante as rev')
                        ->whereColumn('rev.revierte_id', 'tesoreria_crown_comprobante.id');
                })
                ->exists();
            if ($hayHab) {
                return true;
            }
        }

        return false;
    }
}

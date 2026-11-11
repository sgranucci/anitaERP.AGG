<?php

namespace App\Support\Ventas\GastronomiaCrown;

use App\Models\Caja\CajeroCrown;
use App\Models\Caja\TesoreriaCrownAsignacion;
use App\Models\Caja\TesoreriaCrownComprobante;
use App\Models\Ventas\GastronomiaCrown\GastroCrownCuenta;
use App\Models\Ventas\MozoGastronomia;
use App\Support\Caja\TesoreriaCrownComprobanteSupport;
use InvalidArgumentException;

/**
 * Paridad Anita COMAND_verifica_caja_abierta (comandera_view.fc):
 * el mozo/cajero tiene habilitación conforme vigente (caja sin cierre activo)
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

        $asignaciones = TesoreriaCrownAsignacion::query()
            ->where('cajero_crown_id', (int) $cajero->id)
            ->when($salaId !== null && $salaId > 0, fn ($q) => $q->where('sala_id', $salaId))
            ->whereHas('jornada', fn ($q) => $q->whereDate('fecha', $fechaYmd))
            ->whereDoesntHave('cierre')
            ->orderBy('id')
            ->get(['id']);

        if ($asignaciones->isEmpty()) {
            return false;
        }

        return TesoreriaCrownComprobante::query()
            ->whereIn('asignacion_id', $asignaciones->pluck('id')->all())
            ->where('tipo', TesoreriaCrownComprobanteSupport::HABILITACION)
            ->where('estado', TesoreriaCrownComprobanteSupport::CONFORME)
            ->whereNull('revierte_id')
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('tesoreria_crown_comprobante as rev')
                    ->whereColumn('rev.revierte_id', 'tesoreria_crown_comprobante.id');
            })
            ->exists();
    }
}

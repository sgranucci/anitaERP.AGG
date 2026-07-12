<?php

namespace App\Support\Contable;

use App\Models\Contable\Asiento;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use Illuminate\Support\Facades\Auth;

/**
 * Genera un asiento contable invirtiendo debe/haber (patrón copiarAsiento con revierte=1).
 */
final class AsientoReversoSupport
{
    public function __construct(
        private readonly AsientoRepositoryInterface $asientoRepository,
        private readonly Asiento_MovimientoRepositoryInterface $asientoMovimientoRepository,
    ) {}

    /**
     * @return array{asiento_id: int, numeroasiento: string}
     */
    public function generarDesdeAsiento(
        Asiento $asientoOriginal,
        ?string $fecha = null,
        ?int $movimientostockId = null,
        ?string $prefijoObservacion = null
    ): array {
        $asientoOriginal->loadMissing('asiento_movimientos');

        $centrocostoIds = [];
        $debes = [];
        $haberes = [];
        $cuentacontableIds = [];
        $observaciones = [];
        $monedaIds = [];
        $cotizaciones = [];

        foreach ($asientoOriginal->asiento_movimientos as $movimiento) {
            $monto = (float) ($movimiento->monto ?? 0);
            $centrocostoIds[] = $movimiento->centrocosto_id;
            $cuentacontableIds[] = $movimiento->cuentacontable_id;
            $observaciones[] = $movimiento->observacion;
            $monedaIds[] = $movimiento->moneda_id;
            $cotizaciones[] = $movimiento->cotizacion;

            if ($monto >= 0) {
                $haberes[] = $monto;
                $debes[] = 0;
            } else {
                $debes[] = abs($monto);
                $haberes[] = 0;
            }
        }

        $obsBase = trim((string) ($prefijoObservacion ?? 'Revierte asiento '.$asientoOriginal->numeroasiento));
        $observacionCab = $obsBase.' '.trim((string) ($asientoOriginal->observacion ?? ''));

        $payload = [
            'empresa_id' => (int) $asientoOriginal->empresa_id,
            'tipoasiento_id' => (int) $asientoOriginal->tipoasiento_id,
            'fecha' => $fecha ?: ($asientoOriginal->fecha instanceof \DateTimeInterface
                ? $asientoOriginal->fecha->format('Y-m-d')
                : (string) $asientoOriginal->fecha),
            'observacion' => trim($observacionCab),
            'usuario_id' => Auth::id(),
            'centrocosto_ids' => $centrocostoIds,
            'cuentacontable_ids' => $cuentacontableIds,
            'moneda_ids' => $monedaIds,
            'observaciones' => $observaciones,
            'cotizaciones' => $cotizaciones,
            'debes' => $debes,
            'haberes' => $haberes,
            'alcance_cierre_contable' => PeriodoContableCierreSupport::ALCANCE_CONTABLE,
        ];

        if ($movimientostockId > 0) {
            $payload['movimientostock_id'] = $movimientostockId;
        }

        $nuevo = $this->asientoRepository->create($payload);
        if ($nuevo === 'Error' || ! $nuevo) {
            throw new \RuntimeException('Error al grabar asiento contable de reversión.');
        }

        $asientoId = (int) $nuevo->id;
        $this->asientoMovimientoRepository->create($payload, $asientoId);

        return [
            'asiento_id' => $asientoId,
            'numeroasiento' => (string) ($nuevo->numeroasiento ?? ''),
        ];
    }
}

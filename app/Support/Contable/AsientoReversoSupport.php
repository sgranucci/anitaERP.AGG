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
     * @param  array{tipo?: string, letra?: string, sucursal?: int|string, nro?: int|string}|null  $referenciaComprobante
     * @return array{asiento_id: int, numeroasiento: string}
     */
    public function generarDesdeAsiento(
        Asiento $asientoOriginal,
        ?string $fecha = null,
        ?int $movimientostockId = null,
        ?string $prefijoObservacion = null,
        bool $omitirAnita = false,
        ?int $cajaMovimientoId = null,
        ?array $referenciaComprobante = null,
        ?string $alcanceCierre = null,
    ): array {
        $asientoOriginal->loadMissing('asiento_movimientos');

        $centrocostoIds = [];
        $debes = [];
        $haberes = [];
        $cuentacontableIds = [];
        $observaciones = [];
        $monedaIds = [];
        $cotizaciones = [];

        $obsBase = trim((string) ($prefijoObservacion ?? 'Revierte asiento '.$asientoOriginal->numeroasiento));

        foreach ($asientoOriginal->asiento_movimientos as $movimiento) {
            $monto = (float) ($movimiento->monto ?? 0);
            $centrocostoIds[] = $movimiento->centrocosto_id;
            $cuentacontableIds[] = $movimiento->cuentacontable_id;
            $obsLinea = trim((string) ($movimiento->observacion ?? ''));
            $observaciones[] = $obsBase !== ''
                ? ($obsLinea !== '' ? $obsBase.' '.$obsLinea : $obsBase)
                : $obsLinea;
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

        $observacionCab = trim($obsBase.' '.trim((string) ($asientoOriginal->observacion ?? '')));

        $payload = [
            'empresa_id' => (int) $asientoOriginal->empresa_id,
            'tipoasiento_id' => (int) $asientoOriginal->tipoasiento_id,
            'fecha' => $fecha ?: ($asientoOriginal->fecha instanceof \DateTimeInterface
                ? $asientoOriginal->fecha->format('Y-m-d')
                : (string) $asientoOriginal->fecha),
            'observacion' => $observacionCab,
            'usuario_id' => Auth::id(),
            'centrocosto_ids' => $centrocostoIds,
            'cuentacontable_ids' => $cuentacontableIds,
            'moneda_ids' => $monedaIds,
            'observaciones' => $observaciones,
            'cotizaciones' => $cotizaciones,
            'debes' => $debes,
            'haberes' => $haberes,
            // El reverso se valida contra el cierre del módulo de origen cuando el caller lo conoce.
            'alcance_cierre_contable' => $alcanceCierre !== null && $alcanceCierre !== ''
                ? $alcanceCierre
                : PeriodoContableCierreSupport::ALCANCE_CONTABLE,
            // Caller que re-sincroniza ctamov después (ej. TM) evita huérfanos por doble escritura.
            'omitir_anita' => $omitirAnita,
        ];

        if ($movimientostockId > 0) {
            $payload['movimientostock_id'] = $movimientostockId;
        }

        if ($cajaMovimientoId > 0) {
            $payload['caja_movimiento_id'] = $cajaMovimientoId;
        }

        if (is_array($referenciaComprobante)) {
            if (isset($referenciaComprobante['tipo'])) {
                $payload['tipo'] = $referenciaComprobante['tipo'];
            }
            if (array_key_exists('letra', $referenciaComprobante)) {
                $payload['letra'] = $referenciaComprobante['letra'];
            }
            if (array_key_exists('sucursal', $referenciaComprobante)) {
                $payload['sucursal'] = $referenciaComprobante['sucursal'];
            }
            if (array_key_exists('nro', $referenciaComprobante)) {
                $payload['nro'] = $referenciaComprobante['nro'];
            }
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

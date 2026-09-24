<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Caja_Movimiento_Cuentacaja;
use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Repositories\Contable\AsientoRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Detecta y corrige IE (EGR/ING) con asiento Debe/Haber invertido
 * (pierna de cuenta de caja del movimiento en el lado contrario al tipo).
 */
final class IngresoEgresoCorregirAsientoSignoInvertidoSupport
{
    public function __construct(
        private readonly AsientoRepository $asientoRepository,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarInvertidos(string $desde, string $hasta, ?int $empresaId = null): array
    {
        $q = Caja_Movimiento::query()
            ->with([
                'tipotransaccioncajas',
                'asientos.asiento_movimientos.cuentacontables',
                'caja_movimiento_cuentacajas.cuentacajas',
            ])
            ->whereBetween('fecha', [$desde, $hasta])
            ->whereHas('tipotransaccioncajas', function ($tq) {
                $tq->whereIn('operacion', ['E', 'I'])
                    ->whereIn('abreviatura', ['EGR', 'ING']);
            })
            ->whereHas('asientos')
            ->orderBy('fecha')
            ->orderBy('numerotransaccion');

        if ($empresaId !== null && $empresaId > 0) {
            $q->where('empresa_id', $empresaId);
        }

        $out = [];
        foreach ($q->get() as $mov) {
            $plan = $this->planificarMovimiento($mov);
            if ($plan !== null) {
                $out[] = $plan;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $planes
     * @return array{corregidos:int, ctamov:int, caja:int, errores:list<string>}
     */
    public function ejecutar(array $planes, bool $dryRun): array
    {
        $corregidos = 0;
        $ctamov = 0;
        $caja = 0;
        $errores = [];

        foreach ($planes as $plan) {
            try {
                if ($dryRun) {
                    $corregidos++;
                    $ctamov++;
                    $caja += count($plan['caja_cambios'] ?? []);

                    continue;
                }

                DB::transaction(function () use ($plan, &$ctamov, &$caja) {
                    foreach ($plan['lineas_asiento'] as $linea) {
                        Asiento_Movimiento::query()
                            ->where('id', $linea['id'])
                            ->update(['monto' => $linea['monto_nuevo']]);
                    }

                    foreach ($plan['caja_cambios'] as $cambio) {
                        Caja_Movimiento_Cuentacaja::query()
                            ->where('id', $cambio['id'])
                            ->update(['monto' => $cambio['monto_nuevo']]);
                        $caja++;
                    }

                    $asiento = Asiento::query()->findOrFail($plan['asiento_id']);
                    $payload = $this->asientoRepository->armarPayloadAnitaDesdeModelo($asiento->fresh());
                    $this->asientoRepository->sincronizarCtamovAnita($payload);
                    $ctamov++;
                });

                $corregidos++;
            } catch (\Throwable $e) {
                $errores[] = sprintf(
                    'nro %s asiento %s: %s',
                    (string) ($plan['numerotransaccion'] ?? '?'),
                    (string) ($plan['numeroasiento'] ?? '?'),
                    $e->getMessage()
                );
            }
        }

        return compact('corregidos', 'ctamov', 'caja', 'errores');
    }

    /**
     * Corrige signos de caja EGR/ING desalineados sin tocar asientos ya correctos.
     *
     * @return list<array<string, mixed>>
     */
    public function listarCajaSignoIncorrecto(string $desde, string $hasta, ?int $empresaId = null): array
    {
        $q = Caja_Movimiento::query()
            ->with(['tipotransaccioncajas', 'caja_movimiento_cuentacajas'])
            ->whereBetween('fecha', [$desde, $hasta])
            ->whereHas('tipotransaccioncajas', function ($tq) {
                $tq->whereIn('abreviatura', ['EGR', 'ING']);
            })
            ->orderBy('fecha')
            ->orderBy('numerotransaccion');

        if ($empresaId !== null && $empresaId > 0) {
            $q->where('empresa_id', $empresaId);
        }

        $out = [];
        foreach ($q->get() as $mov) {
            /** @var Tipotransaccion_Caja|null $tipo */
            $tipo = $mov->tipotransaccioncajas;
            if ($tipo === null || IngresoEgresoTransferenciaSupport::esTransferencia($tipo)) {
                continue;
            }

            $signoEsperado = IngresoEgresoCajaMontoSignoSupport::signoPersistencia($tipo);
            $cambios = [];
            foreach ($mov->caja_movimiento_cuentacajas as $linea) {
                $monto = (float) $linea->monto;
                if (abs($monto) < 0.01) {
                    continue;
                }
                $nuevo = round(abs($monto) * $signoEsperado, 2);
                if (abs($nuevo - $monto) < 0.01) {
                    continue;
                }
                $cambios[] = [
                    'id' => (int) $linea->id,
                    'monto_actual' => $monto,
                    'monto_nuevo' => $nuevo,
                ];
            }
            if ($cambios === []) {
                continue;
            }

            $out[] = [
                'caja_movimiento_id' => (int) $mov->id,
                'numerotransaccion' => (string) $mov->numerotransaccion,
                'fecha' => (string) $mov->fecha,
                'detalle' => (string) ($mov->detalle ?? ''),
                'abreviatura' => (string) ($tipo->abreviatura ?? ''),
                'caja_cambios' => $cambios,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $planes
     * @return array{corregidos:int, errores:list<string>}
     */
    public function ejecutarSoloCaja(array $planes, bool $dryRun): array
    {
        $corregidos = 0;
        $errores = [];

        foreach ($planes as $plan) {
            try {
                if ($dryRun) {
                    $corregidos++;

                    continue;
                }
                foreach ($plan['caja_cambios'] as $cambio) {
                    Caja_Movimiento_Cuentacaja::query()
                        ->where('id', $cambio['id'])
                        ->update(['monto' => $cambio['monto_nuevo']]);
                }
                $corregidos++;
            } catch (\Throwable $e) {
                $errores[] = sprintf(
                    'nro %s: %s',
                    (string) ($plan['numerotransaccion'] ?? '?'),
                    $e->getMessage()
                );
            }
        }

        return compact('corregidos', 'errores');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function planificarMovimiento(Caja_Movimiento $mov): ?array
    {
        /** @var Tipotransaccion_Caja|null $tipo */
        $tipo = $mov->tipotransaccioncajas;
        if ($tipo === null) {
            return null;
        }

        $abrev = strtoupper(trim((string) ($tipo->abreviatura ?? '')));
        if (! in_array($abrev, ['EGR', 'ING'], true)) {
            return null;
        }

        /** @var Asiento|null $asiento */
        $asiento = $mov->asientos instanceof Collection
            ? $mov->asientos->first()
            : $mov->asientos;
        if ($asiento === null) {
            return null;
        }

        $cuentasCajaIds = [];
        foreach ($mov->caja_movimiento_cuentacajas as $cmc) {
            $ctaId = (int) ($cmc->cuentacajas->cuentacontable_id ?? 0);
            if ($ctaId > 0) {
                $cuentasCajaIds[$ctaId] = true;
            }
        }
        if ($cuentasCajaIds === []) {
            return null;
        }

        $piernasCaja = [];
        foreach ($asiento->asiento_movimientos as $am) {
            $ctaId = (int) $am->cuentacontable_id;
            if (! isset($cuentasCajaIds[$ctaId])) {
                continue;
            }
            $piernasCaja[] = (float) $am->monto;
        }
        if ($piernasCaja === []) {
            return null;
        }

        $hayDebe = false;
        $hayHaber = false;
        foreach ($piernasCaja as $monto) {
            if ($monto > 0.01) {
                $hayDebe = true;
            }
            if ($monto < -0.01) {
                $hayHaber = true;
            }
        }

        // EGR: pierna caja debe estar en Haber. Invertido = solo Debe.
        // ING: pierna caja debe estar en Debe. Invertido = solo Haber.
        $invertido = ($abrev === 'EGR' && $hayDebe && ! $hayHaber)
            || ($abrev === 'ING' && $hayHaber && ! $hayDebe);

        if (! $invertido) {
            return null;
        }

        $lineas = [];
        foreach ($asiento->asiento_movimientos as $am) {
            $actual = (float) $am->monto;
            $lineas[] = [
                'id' => (int) $am->id,
                'cuenta' => (string) ($am->cuentacontables->codigo ?? $am->cuentacontable_id),
                'monto_actual' => $actual,
                'monto_nuevo' => round(-1 * $actual, 2),
            ];
        }

        $signoEsperado = IngresoEgresoCajaMontoSignoSupport::signoPersistencia($tipo);
        $cajaCambios = [];
        foreach ($mov->caja_movimiento_cuentacajas as $cmc) {
            $monto = (float) $cmc->monto;
            if (abs($monto) < 0.01) {
                continue;
            }
            $nuevo = round(abs($monto) * $signoEsperado, 2);
            if (abs($nuevo - $monto) < 0.01) {
                continue;
            }
            $cajaCambios[] = [
                'id' => (int) $cmc->id,
                'monto_actual' => $monto,
                'monto_nuevo' => $nuevo,
            ];
        }

        return [
            'caja_movimiento_id' => (int) $mov->id,
            'numerotransaccion' => (string) $mov->numerotransaccion,
            'fecha' => (string) $mov->fecha,
            'detalle' => (string) ($mov->detalle ?? ''),
            'abreviatura' => $abrev,
            'asiento_id' => (int) $asiento->id,
            'numeroasiento' => (string) $asiento->numeroasiento,
            'empresa_id' => (int) $asiento->empresa_id,
            'lineas_asiento' => $lineas,
            'caja_cambios' => $cajaCambios,
        ];
    }
}

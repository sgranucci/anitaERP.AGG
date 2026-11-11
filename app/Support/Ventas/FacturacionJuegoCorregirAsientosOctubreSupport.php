<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Caja\Flash\FlashCrown;
use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Models\Ventas\Venta;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Corrige asientos de facturación juego emitidos con signo invertido en créditos
 * (CME/CSL/CRE) y backfill de referencia al comprobante en mayor.
 */
final class FacturacionJuegoCorregirAsientosOctubreSupport
{
    private const TIPOS_CREDITO = ['CME', 'CSL', 'CRE'];

    /**
     * @return list<array<string, mixed>>
     */
    public function planificar(string $fechaDesde, string $fechaHasta, ?int $salaId = null): array
    {
        $planes = [];
        foreach ($this->flashesRango($fechaDesde, $fechaHasta, $salaId) as $flash) {
            foreach ($this->rubros() as $rubro) {
                $plan = $this->planRubro($flash, $rubro);
                if ($plan !== null) {
                    $planes[] = $plan;
                }
            }
        }

        return $planes;
    }

    /**
     * @param  list<array<string, mixed>>  $planes
     * @return array{invertidos:int, referencias:int, errores:list<string>}
     */
    public function ejecutar(array $planes, bool $dryRun): array
    {
        $invertidos = 0;
        $referencias = 0;
        $errores = [];

        foreach ($planes as $plan) {
            try {
                if ($dryRun) {
                    if (! empty($plan['invertir_signo'])) {
                        $invertidos++;
                    } elseif (! empty($plan['actualizar_referencia'])) {
                        $referencias++;
                    }
                    continue;
                }

                DB::transaction(function () use ($plan, &$invertidos, &$referencias) {
                    $asientoId = (int) $plan['asiento_id'];
                    $concepto = (string) $plan['concepto_nuevo'];

                    if (! empty($plan['invertir_signo'])) {
                        Asiento_Movimiento::query()
                            ->where('asiento_id', $asientoId)
                            ->get()
                            ->each(function (Asiento_Movimiento $mov) use ($concepto) {
                                $mov->monto = round(-1 * (float) $mov->monto, 4);
                                $mov->observacion = $concepto;
                                $mov->save();
                            });
                        $invertidos++;
                    } elseif (! empty($plan['actualizar_referencia'])) {
                        Asiento_Movimiento::query()
                            ->where('asiento_id', $asientoId)
                            ->get()
                            ->each(function (Asiento_Movimiento $mov) use ($concepto) {
                                if ((string) ($mov->observacion ?? '') === $concepto) {
                                    return;
                                }
                                $mov->observacion = $concepto;
                                $mov->save();
                            });
                        $referencias++;
                    }

                    $asiento = Asiento::query()->whereKey($asientoId)->first();
                    if ($asiento !== null) {
                        $dirty = false;
                        if ((string) ($asiento->observacion ?? '') !== $concepto) {
                            $asiento->observacion = $concepto;
                            $dirty = true;
                        }
                        $ventaId = (int) ($plan['venta_id'] ?? 0);
                        if ($ventaId > 0 && (int) ($asiento->venta_id ?? 0) !== $ventaId) {
                            $asiento->venta_id = $ventaId;
                            $dirty = true;
                        }
                        if ($dirty) {
                            $asiento->save();
                        }
                    }
                });
            } catch (\Throwable $e) {
                $errores[] = 'Asiento #'.($plan['asiento_id'] ?? '?').': '.$e->getMessage();
            }
        }

        return compact('invertidos', 'referencias', 'errores');
    }

    /**
     * @return array{
     *   total:int,
     *   a_invertir:int,
     *   a_referenciar:int,
     *   por_sala:array<string,array{invertir:int,referenciar:int}>,
     *   importe_invertir:float
     * }
     */
    public function resumen(array $planes): array
    {
        $porSala = [];
        $aInvertir = 0;
        $aReferenciar = 0;
        $importe = 0.0;

        foreach ($planes as $plan) {
            $sala = (string) ($plan['sala_codigo'] ?? '?');
            if (! isset($porSala[$sala])) {
                $porSala[$sala] = ['invertir' => 0, 'referenciar' => 0];
            }
            if (! empty($plan['invertir_signo'])) {
                $aInvertir++;
                $porSala[$sala]['invertir']++;
                $importe += abs((float) ($plan['monto_ventas_actual'] ?? 0));
            } elseif (! empty($plan['actualizar_referencia'])) {
                $aReferenciar++;
                $porSala[$sala]['referenciar']++;
            }
        }

        ksort($porSala);

        return [
            'total' => count($planes),
            'a_invertir' => $aInvertir,
            'a_referenciar' => $aReferenciar,
            'por_sala' => $porSala,
            'importe_invertir' => round($importe, 2),
        ];
    }

    /**
     * @return Collection<int, FlashCrown>
     */
    private function flashesRango(string $fechaDesde, string $fechaHasta, ?int $salaId): Collection
    {
        $q = FlashCrown::query()
            ->with(['sala:id,codigo,nombre'])
            ->whereDate('fecha', '>=', $fechaDesde)
            ->whereDate('fecha', '<=', $fechaHasta)
            ->where(function ($w) {
                $w->where('proc_mesa', '*')
                    ->orWhere('proc_slot', '*')
                    ->orWhere('proc_ruleta_el', '*');
            })
            ->orderBy('fecha')
            ->orderBy('sala_id');

        if ($salaId !== null && $salaId > 0) {
            $q->where('sala_id', $salaId);
        }

        return $q->get();
    }

    /**
     * @return list<string>
     */
    private function rubros(): array
    {
        return [
            FacturacionJuegoTipoSupport::RUBRO_MESA,
            FacturacionJuegoTipoSupport::RUBRO_SLOT,
            FacturacionJuegoTipoSupport::RUBRO_RULETA_EL,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function planRubro(FlashCrown $flash, string $rubro): ?array
    {
        [$ventaId, $asientoId] = match ($rubro) {
            FacturacionJuegoTipoSupport::RUBRO_MESA => [
                (int) ($flash->venta_mesa_id ?? 0),
                (int) ($flash->asiento_mesa_id ?? 0),
            ],
            FacturacionJuegoTipoSupport::RUBRO_SLOT => [
                (int) ($flash->venta_slot_id ?? 0),
                (int) ($flash->asiento_slot_id ?? 0),
            ],
            FacturacionJuegoTipoSupport::RUBRO_RULETA_EL => [
                (int) ($flash->venta_ruleta_el_id ?? 0),
                (int) ($flash->asiento_ruleta_el_id ?? 0),
            ],
            default => [0, 0],
        };

        if ($ventaId <= 0 || $asientoId <= 0) {
            return null;
        }

        $venta = Venta::query()
            ->with(['tipotransacciones:id,abreviatura,signo'])
            ->whereKey($ventaId)
            ->first(['id', 'codigo', 'nombre', 'tipotransaccion_id', 'total']);
        if ($venta === null) {
            return null;
        }

        $abrev = strtoupper(trim((string) ($venta->tipotransacciones->abreviatura ?? '')));
        $esCredito = in_array($abrev, self::TIPOS_CREDITO, true);
        $nombreCliente = (string) ($venta->nombre ?: FacturacionJuegoTipoSupport::nombreCliente($rubro));
        $conceptoNuevo = FacturacionJuegoTipoSupport::conceptoMayor((string) $venta->codigo, $nombreCliente);

        $cfg = FacturacionJuegoConfigSupport::salaPorSalaId((int) $flash->sala_id);
        $cuentaVentaId = match ($rubro) {
            FacturacionJuegoTipoSupport::RUBRO_MESA => (int) ($cfg->cuentacontable_mesa_id ?? 0),
            FacturacionJuegoTipoSupport::RUBRO_SLOT => (int) ($cfg->cuentacontable_slot_id ?? 0),
            FacturacionJuegoTipoSupport::RUBRO_RULETA_EL => (int) ($cfg->cuentacontable_ruleta_el_id ?? 0),
            default => 0,
        };

        $movVenta = Asiento_Movimiento::query()
            ->where('asiento_id', $asientoId)
            ->when($cuentaVentaId > 0, fn ($q) => $q->where('cuentacontable_id', $cuentaVentaId))
            ->orderBy('id')
            ->first();

        $montoVentas = $movVenta !== null ? (float) $movVenta->monto : 0.0;
        // Crédito Anita: ventas en DEBE (monto > 0). Si está en HABER (monto < 0) → invertido.
        $invertir = $esCredito && $montoVentas < -0.009;

        $obsActual = (string) ($movVenta->observacion ?? '');
        $obsCab = (string) (Asiento::query()->whereKey($asientoId)->value('observacion') ?? '');
        $ventaIdAsiento = (int) (Asiento::query()->whereKey($asientoId)->value('venta_id') ?? 0);
        $actualizarRef = $obsActual !== $conceptoNuevo
            || $obsCab !== $conceptoNuevo
            || $ventaIdAsiento !== $ventaId;

        if (! $invertir && ! $actualizarRef) {
            return null;
        }

        return [
            'flash_id' => (int) $flash->id,
            'fecha' => Carbon::parse((string) $flash->fecha)->toDateString(),
            'sala_id' => (int) $flash->sala_id,
            'sala_codigo' => (string) ($flash->sala->codigo ?? ''),
            'rubro' => $rubro,
            'tipo' => $abrev,
            'venta_id' => $ventaId,
            'codigo' => (string) $venta->codigo,
            'asiento_id' => $asientoId,
            'monto_ventas_actual' => $montoVentas,
            'monto_ventas_nuevo' => $invertir ? round(-1 * $montoVentas, 4) : $montoVentas,
            'concepto_actual' => $obsActual,
            'concepto_nuevo' => $conceptoNuevo,
            'invertir_signo' => $invertir,
            'actualizar_referencia' => $actualizarRef,
        ];
    }
}

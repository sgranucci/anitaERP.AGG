<?php

namespace App\Support\Configuracion;

use App\Models\Compras\Requisicion;
use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_ReTrigger;
use App\Queries\Configuracion\CotizacionQueryInterface;
use App\Support\Compras\RequisicionTotalesCabecera;
use App\Support\Configuracion\ReArbolTriggerEvaluators\ReArbolTriggerEvalContext;
use App\Support\Configuracion\ReArbolTriggerEvaluators\ReArbolTriggerEvaluatorRegistry;
use Illuminate\Support\Facades\Schema;

/**
 * Resuelve rama RE: triggers por prioridad → dual-rama/allowlist → circuito único.
 */
final class ReArbolTriggerResolver
{
    /**
     * @return array{rama: ?string, trigger_id: ?int, origen: string}
     */
    public static function resolver(Arbolaprobacion $arbol, Requisicion $requisicion, int $centrocostoArbol): array
    {
        $fallback = [
            'rama' => ReArbolRamaSupport::resolverRama($arbol, $requisicion, $centrocostoArbol),
            'trigger_id' => null,
            'origen' => 'dual_rama_o_clasico',
        ];

        if (! Schema::hasTable('arbolaprobacion_re_trigger')) {
            return $fallback;
        }

        try {
            $triggers = Arbolaprobacion_ReTrigger::query()
                ->where('arbolaprobacion_id', (int) $arbol->id)
                ->where('activo', 'S')
                ->orderBy('prioridad')
                ->orderBy('id')
                ->get();
        } catch (\Throwable $e) {
            return $fallback;
        }

        if ($triggers->isEmpty()) {
            return $fallback;
        }

        $fechaYmd = $requisicion->fecha
            ? substr((string) $requisicion->fecha, 0, 10)
            : date('Y-m-d');

        $totales = ['monto' => 0.0, 'moneda_id' => 1];
        try {
            $totales = RequisicionTotalesCabecera::desdeModelo(
                $requisicion,
                app(CotizacionQueryInterface::class)
            );
        } catch (\Throwable $e) {
            // Sin cotización: evaluadores de monto no aplicarán de forma fiable; el resto sí.
        }

        $ctx = new ReArbolTriggerEvalContext(
            $arbol,
            $requisicion,
            $centrocostoArbol,
            (float) ($totales['monto'] ?? 0),
            (int) ($totales['moneda_id'] ?? 1),
            $fechaYmd,
        );

        $registry = app(ReArbolTriggerEvaluatorRegistry::class);

        foreach ($triggers as $trigger) {
            $ccCfg = (int) ($trigger->centrocosto_id ?? 0);
            if ($ccCfg > 0 && $ccCfg !== $centrocostoArbol) {
                continue;
            }

            $desde = $trigger->vigencia_desde;
            $hasta = $trigger->vigencia_hasta;
            $desdeStr = $desde ? (is_object($desde) ? $desde->format('Y-m-d') : substr((string) $desde, 0, 10)) : null;
            $hastaStr = $hasta ? (is_object($hasta) ? $hasta->format('Y-m-d') : substr((string) $hasta, 0, 10)) : null;

            if (! ReArbolTriggerCatalog::vigenciaAplica($desdeStr, $hastaStr, $fechaYmd)) {
                continue;
            }

            if (! $registry->aplica($ctx, $trigger)) {
                continue;
            }

            $accion = ReArbolTriggerCatalog::normalizarAccionRama($trigger->accion_rama ?? null);
            $rama = match ($accion) {
                ReArbolTriggerCatalog::ACCION_RAMA_A => ReArbolRamaCatalog::RAMA_A,
                ReArbolTriggerCatalog::ACCION_RAMA_B => ReArbolRamaCatalog::RAMA_B,
                default => ReArbolRamaSupport::resolverRama($arbol, $requisicion, $centrocostoArbol)
                    ?? ReArbolRamaCatalog::RAMA_B,
            };

            return [
                'rama' => $rama,
                'trigger_id' => (int) $trigger->id,
                'origen' => 'trigger',
            ];
        }

        return $fallback;
    }
}

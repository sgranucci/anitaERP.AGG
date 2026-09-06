<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Arbolaprobacion_ReTrigger;
use App\Support\Configuracion\ReArbolTriggerCatalog;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Support\Facades\Schema;

class Arbolaprobacion_ReTriggerRepository implements Arbolaprobacion_ReTriggerRepositoryInterface
{
    public function syncFromRequest(array $data, int $arbolaprobacionId): void
    {
        if (! Schema::hasTable('arbolaprobacion_re_trigger')) {
            return;
        }

        if (! isset($data['re_trigger_ids']) || ! is_array($data['re_trigger_ids'])) {
            $data['re_trigger_ids'] = [];
        }

        $ids = $data['re_trigger_ids'];
        $count = count($ids);
        $guardados = [];

        for ($i = 0; $i < $count; $i++) {
            $evaluador = strtoupper(trim((string) ($data['re_trigger_evaluadores'][$i] ?? '')));
            if ($evaluador === '' || ! in_array($evaluador, ReArbolTriggerCatalog::evaluadores(), true)) {
                continue;
            }

            $rowId = isset($ids[$i]) && $ids[$i] !== '' ? (int) $ids[$i] : null;
            $ccId = (int) ($data['re_trigger_centrocosto_ids'][$i] ?? 0);
            $accion = ReArbolTriggerCatalog::normalizarAccionRama($data['re_trigger_acciones'][$i] ?? null);
            $activo = strtoupper(trim((string) ($data['re_trigger_activos'][$i] ?? 'S'))) === 'N' ? 'N' : 'S';

            $montoRaw = $data['re_trigger_param_montos'][$i] ?? null;
            $paramMonto = ($montoRaw === '' || $montoRaw === null) ? null : (float) $montoRaw;
            $monedaId = (int) ($data['re_trigger_param_moneda_ids'][$i] ?? 0);
            $cuentaId = (int) ($data['re_trigger_param_cuentacontable_ids'][$i] ?? 0);

            if (! ReArbolTriggerCatalog::usaMonto($evaluador)) {
                $paramMonto = null;
                $monedaId = 0;
            }
            if (! ReArbolTriggerCatalog::usaCuenta($evaluador)) {
                $cuentaId = 0;
            }

            $desde = trim((string) ($data['re_trigger_vigencia_desdes'][$i] ?? ''));
            $hasta = trim((string) ($data['re_trigger_vigencia_hastas'][$i] ?? ''));

            $payload = [
                'arbolaprobacion_id' => $arbolaprobacionId,
                'nombre' => trim((string) ($data['re_trigger_nombres'][$i] ?? '')) ?: null,
                'tipo' => ReArbolTriggerCatalog::TIPO_CONDICION,
                'evaluador' => $evaluador,
                'centrocosto_id' => $ccId > 0 ? $ccId : null,
                'accion_rama' => $accion,
                'param_monto' => $paramMonto,
                'param_moneda_id' => $monedaId > 0 ? $monedaId : null,
                'param_cuentacontable_id' => $cuentaId > 0 ? $cuentaId : null,
                'vigencia_desde' => $desde !== '' ? $desde : null,
                'vigencia_hasta' => $hasta !== '' ? $hasta : null,
                'observacion' => trim((string) ($data['re_trigger_observaciones'][$i] ?? '')) ?: null,
                'prioridad' => max(1, (int) ($data['re_trigger_prioridades'][$i] ?? 100)),
                'activo' => $activo,
            ];

            if ($rowId) {
                $existente = Arbolaprobacion_ReTrigger::query()
                    ->where('id', $rowId)
                    ->where('arbolaprobacion_id', $arbolaprobacionId)
                    ->first();
                if ($existente) {
                    $existente->update($payload);
                    $guardados[] = $rowId;

                    continue;
                }
            }

            $creado = Arbolaprobacion_ReTrigger::query()->create($payload);
            $guardados[] = (int) $creado->id;
        }

        $query = Arbolaprobacion_ReTrigger::query()->where('arbolaprobacion_id', $arbolaprobacionId);
        if ($guardados !== []) {
            $query->whereNotIn('id', $guardados);
        }
        EloquentAuditDeleteSupport::each($query);
    }

    public function deleteByArbol(int $arbolaprobacionId): void
    {
        if (! Schema::hasTable('arbolaprobacion_re_trigger')) {
            return;
        }

        EloquentAuditDeleteSupport::each(
            Arbolaprobacion_ReTrigger::query()->where('arbolaprobacion_id', $arbolaprobacionId)
        );
    }
}

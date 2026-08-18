<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Arbolaprobacion_OcTrigger;
use App\Support\Configuracion\OcArbolTriggerCatalog;
use App\Support\Database\EloquentAuditDeleteSupport;

class Arbolaprobacion_OcTriggerRepository implements Arbolaprobacion_OcTriggerRepositoryInterface
{
    public function syncFromRequest(array $data, int $arbolaprobacionId): void
    {
        if (! isset($data['oc_trigger_ids']) || ! is_array($data['oc_trigger_ids'])) {
            $data['oc_trigger_ids'] = [];
        }

        $ids = $data['oc_trigger_ids'];
        $count = count($ids);
        $guardados = [];

        for ($i = 0; $i < $count; $i++) {
            $rowId = isset($ids[$i]) && $ids[$i] !== '' ? (int) $ids[$i] : null;
            $tipo = strtoupper(trim((string) ($data['oc_trigger_tipos'][$i] ?? '')));
            if ($tipo === '' || ! in_array($tipo, OcArbolTriggerCatalog::tipos(), true)) {
                continue;
            }

            $evento = null;
            $evaluador = null;
            if ($tipo === OcArbolTriggerCatalog::TIPO_EVENTO) {
                $evento = trim((string) ($data['oc_trigger_eventos'][$i] ?? '')) ?: null;
            } else {
                $evaluador = trim((string) ($data['oc_trigger_evaluadores'][$i] ?? '')) ?: null;
            }

            $payload = [
                'arbolaprobacion_id' => $arbolaprobacionId,
                'nombre' => trim((string) ($data['oc_trigger_nombres'][$i] ?? '')) ?: null,
                'tipo' => $tipo,
                'evento' => $evento,
                'evaluador' => $evaluador,
                'sector_origen_id' => $this->nullableInt($data['oc_trigger_sector_origen_ids'][$i] ?? null),
                'sector_destino_id' => $this->nullableInt($data['oc_trigger_sector_destino_ids'][$i] ?? null),
                'centrocosto_circuito_id' => $this->nullableInt($data['oc_trigger_centrocosto_ids'][$i] ?? null),
                'documento_estado_al_aprobar' => trim((string) ($data['oc_trigger_estados'][$i] ?? '')) ?: null,
                'accion_final' => strtoupper(trim((string) ($data['oc_trigger_acciones'][$i] ?? OcArbolTriggerCatalog::ACCION_NINGUNA))),
                'accion_final_sector_id' => $this->nullableInt($data['oc_trigger_accion_sector_ids'][$i] ?? null),
                'accion_final_estado' => trim((string) ($data['oc_trigger_accion_estados'][$i] ?? '')) ?: null,
                'prioridad' => max(1, (int) ($data['oc_trigger_prioridades'][$i] ?? 100)),
                'anula_auto_aprobacion' => $this->sn($data['oc_trigger_anula_auto'][$i] ?? 'N'),
                'reevaluar_en_actualizacion' => $this->sn($data['oc_trigger_reevaluar'][$i] ?? 'N'),
                'activo' => $this->sn($data['oc_trigger_activos'][$i] ?? 'S'),
            ];

            if ($rowId) {
                $existente = Arbolaprobacion_OcTrigger::query()
                    ->where('id', $rowId)
                    ->where('arbolaprobacion_id', $arbolaprobacionId)
                    ->first();
                if ($existente) {
                    $existente->update($payload);
                    $guardados[] = $rowId;

                    continue;
                }
            }

            $creado = Arbolaprobacion_OcTrigger::create($payload);
            $guardados[] = $creado->id;
        }

        if ($guardados === []) {
            return;
        }

        EloquentAuditDeleteSupport::each(
            Arbolaprobacion_OcTrigger::query()
                ->where('arbolaprobacion_id', $arbolaprobacionId)
                ->whereNotIn('id', $guardados)
        );
    }

    private function nullableInt(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }

        $n = (int) $v;

        return $n > 0 ? $n : null;
    }

    private function sn(mixed $v): string
    {
        return strtoupper((string) $v) === 'S' ? 'S' : 'N';
    }
}

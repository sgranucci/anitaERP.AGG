<?php

use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_ReTrigger;
use App\Models\Contable\Centrocosto;
use App\Support\Configuracion\ReArbolTriggerCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Enriquece triggers RE piloto CeCo 85: línea sin cuenta + auditoría (apagada).
 * El umbral 5M lo resuelven firmantes N2 en niveles Rama B (no trigger).
 * Conserva las reglas allowlist existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('arbolaprobacion_re_trigger')
            || ! Schema::hasColumn('arbolaprobacion_re_trigger', 'param_monto')) {
            return;
        }

        $ccId = (int) (Centrocosto::query()->where('codigo', '85')->value('id') ?: 0);
        if ($ccId <= 0) {
            return;
        }

        $arbolIds = Arbolaprobacion::query()
            ->where('tipoarbol', 'Requisiciones')
            ->whereIn('empresa_id', [1, 2, 3])
            ->pluck('id');

        foreach ($arbolIds as $arbolId) {
            $arbolId = (int) $arbolId;

            Arbolaprobacion_ReTrigger::query()->updateOrCreate(
                [
                    'arbolaprobacion_id' => $arbolId,
                    'evaluador' => ReArbolTriggerCatalog::EVAL_LINEA_SIN_CUENTA,
                    'centrocosto_id' => $ccId,
                ],
                [
                    'nombre' => 'Gastronomía: línea sin cuenta → Rama B',
                    'tipo' => ReArbolTriggerCatalog::TIPO_CONDICION,
                    'accion_rama' => ReArbolTriggerCatalog::ACCION_RAMA_B,
                    'prioridad' => 5,
                    'activo' => 'S',
                    'observacion' => 'RE mal imputada no puede ir por auto.',
                ]
            );

            Arbolaprobacion_ReTrigger::query()->updateOrCreate(
                [
                    'arbolaprobacion_id' => $arbolId,
                    'evaluador' => ReArbolTriggerCatalog::EVAL_SIEMPRE,
                    'centrocosto_id' => $ccId,
                    'nombre' => 'Gastronomía: auditoría — todo a Rama B',
                ],
                [
                    'tipo' => ReArbolTriggerCatalog::TIPO_CONDICION,
                    'accion_rama' => ReArbolTriggerCatalog::ACCION_RAMA_B,
                    'prioridad' => 1,
                    'activo' => 'N',
                    'observacion' => 'Activar + vigencia solo durante auditoría. No borra la allowlist.',
                ]
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('arbolaprobacion_re_trigger')) {
            return;
        }

        $ccId = (int) (Centrocosto::query()->where('codigo', '85')->value('id') ?: 0);
        $arbolIds = Arbolaprobacion::query()
            ->where('tipoarbol', 'Requisiciones')
            ->whereIn('empresa_id', [1, 2, 3])
            ->pluck('id');

        Arbolaprobacion_ReTrigger::query()
            ->whereIn('arbolaprobacion_id', $arbolIds)
            ->where('centrocosto_id', $ccId)
            ->where('evaluador', ReArbolTriggerCatalog::EVAL_LINEA_SIN_CUENTA)
            ->delete();

        Arbolaprobacion_ReTrigger::query()
            ->whereIn('arbolaprobacion_id', $arbolIds)
            ->where('centrocosto_id', $ccId)
            ->where('evaluador', ReArbolTriggerCatalog::EVAL_SIEMPRE)
            ->where('nombre', 'Gastronomía: auditoría — todo a Rama B')
            ->delete();
    }
};

<?php

use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_ReTrigger;
use App\Models\Contable\Centrocosto;
use App\Support\Configuracion\ReArbolTriggerCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Semilla triggers RE para CC 85: fuera allowlist → Rama B; todas en allowlist → Rama A.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('arbolaprobacion_re_trigger')) {
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
                    'evaluador' => ReArbolTriggerCatalog::EVAL_CUENTAS_ALLOWLIST_ALGUNA_FUERA,
                    'centrocosto_id' => $ccId,
                ],
                [
                    'nombre' => 'Gastronomía: fuera de allowlist → Rama B',
                    'tipo' => ReArbolTriggerCatalog::TIPO_CONDICION,
                    'accion_rama' => ReArbolTriggerCatalog::ACCION_RAMA_B,
                    'prioridad' => 10,
                    'activo' => 'S',
                ]
            );

            Arbolaprobacion_ReTrigger::query()->updateOrCreate(
                [
                    'arbolaprobacion_id' => $arbolId,
                    'evaluador' => ReArbolTriggerCatalog::EVAL_CUENTAS_ALLOWLIST_TODAS,
                    'centrocosto_id' => $ccId,
                ],
                [
                    'nombre' => 'Gastronomía: allowlist → Rama A',
                    'tipo' => ReArbolTriggerCatalog::TIPO_CONDICION,
                    'accion_rama' => ReArbolTriggerCatalog::ACCION_RAMA_A,
                    'prioridad' => 20,
                    'activo' => 'S',
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
            ->whereIn('evaluador', [
                ReArbolTriggerCatalog::EVAL_CUENTAS_ALLOWLIST_ALGUNA_FUERA,
                ReArbolTriggerCatalog::EVAL_CUENTAS_ALLOWLIST_TODAS,
            ])
            ->delete();
    }
};

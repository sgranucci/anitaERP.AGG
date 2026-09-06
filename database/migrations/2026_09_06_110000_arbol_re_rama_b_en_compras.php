<?php

use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_Nivel;
use App\Models\Contable\Centrocosto;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rama B: nivel 1 auto → EN COMPRAS; nivel 2 firmantes por monto (hdattilo / mbmendez).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('arbolaprobacion_nivel')
            || ! Schema::hasColumn('arbolaprobacion_nivel', 'rama')) {
            return;
        }

        $ccId = (int) (Centrocosto::query()->where('codigo', '85')->value('id') ?: 0);
        if ($ccId <= 0) {
            return;
        }

        $hdattilo = (int) (Usuario::query()->where('usuario', 'hdattilo')->value('id') ?: 0);
        $mbmendez = (int) (Usuario::query()->where('usuario', 'mbmendez')->value('id') ?: 0);
        if ($hdattilo <= 0 || $mbmendez <= 0) {
            return;
        }

        $arbolIds = Arbolaprobacion::query()
            ->where('tipoarbol', 'Requisiciones')
            ->whereIn('empresa_id', [1, 2, 3])
            ->pluck('id');

        $monedaId = (int) (DB::table('arbolaprobacion_nivel')
            ->whereIn('arbolaprobacion_id', $arbolIds->all())
            ->where('centrocosto_id', $ccId)
            ->value('moneda_id') ?: 1);

        foreach ($arbolIds as $arbolId) {
            $arbolId = (int) $arbolId;

            Arbolaprobacion_Nivel::query()
                ->where('arbolaprobacion_id', $arbolId)
                ->where('centrocosto_id', $ccId)
                ->where('rama', 'B')
                ->delete();

            Arbolaprobacion_Nivel::query()->create([
                'arbolaprobacion_id' => $arbolId,
                'centrocosto_id' => $ccId,
                'nivel' => 1,
                'usuario_id' => null,
                'desdemonto' => 0,
                'hastamonto' => '999999999999999999.9999',
                'moneda_id' => $monedaId,
                'documento_estado_al_aprobar' => 'EN COMPRAS',
                'doble_aprobacion' => 'N',
                'rama' => 'B',
            ]);

            Arbolaprobacion_Nivel::query()->create([
                'arbolaprobacion_id' => $arbolId,
                'centrocosto_id' => $ccId,
                'nivel' => 2,
                'usuario_id' => $hdattilo,
                'desdemonto' => 0,
                'hastamonto' => 5000000,
                'moneda_id' => $monedaId,
                'documento_estado_al_aprobar' => 'APROBADA',
                'doble_aprobacion' => 'N',
                'rama' => 'B',
            ]);

            Arbolaprobacion_Nivel::query()->create([
                'arbolaprobacion_id' => $arbolId,
                'centrocosto_id' => $ccId,
                'nivel' => 2,
                'usuario_id' => $mbmendez,
                'desdemonto' => 5000000.01,
                'hastamonto' => '999999999999999999.9999',
                'moneda_id' => $monedaId,
                'documento_estado_al_aprobar' => 'APROBADA',
                'doble_aprobacion' => 'N',
                'rama' => 'B',
            ]);
        }
    }

    public function down(): void
    {
        // No revierte a la versión intermedia sin EN COMPRAS; el seed original ya quedó atrás.
    }
};

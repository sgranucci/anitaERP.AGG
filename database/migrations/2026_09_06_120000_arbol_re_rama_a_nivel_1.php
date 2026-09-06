<?php

use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_Nivel;
use App\Models\Contable\Centrocosto;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Rama A (auto APROBADA) del CC 85: normalizar a nivel 1 para ordenamiento.
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

        $arbolIds = Arbolaprobacion::query()
            ->where('tipoarbol', 'Requisiciones')
            ->whereIn('empresa_id', [1, 2, 3])
            ->pluck('id');

        Arbolaprobacion_Nivel::query()
            ->whereIn('arbolaprobacion_id', $arbolIds)
            ->where('centrocosto_id', $ccId)
            ->where('rama', 'A')
            ->update(['nivel' => 1]);
    }

    public function down(): void
    {
        // No-op: el nivel histórico (2) no aporta valor al revertir.
    }
};

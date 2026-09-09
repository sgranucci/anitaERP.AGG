<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * En árboles tipo Pedidos (PE), niveles: CC nulo (= todos) y sin Estado doc.
 * Aplica a todos los clientes / instalaciones (no gated por EMPRESA).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('arbolaprobacion') || ! Schema::hasTable('arbolaprobacion_nivel')) {
            return;
        }

        $arbolIds = DB::table('arbolaprobacion')
            ->where('tipoarbol', 'Pedidos')
            ->pluck('id');

        if ($arbolIds->isEmpty()) {
            return;
        }

        DB::table('arbolaprobacion_nivel')
            ->whereIn('arbolaprobacion_id', $arbolIds->all())
            ->update([
                'centrocosto_id' => null,
                'documento_estado_al_aprobar' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // No restaura CC/estado previos.
    }
};

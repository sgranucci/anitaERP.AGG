<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ESTADO_APROBADA = 'APROBADA';

    public function up(): void
    {
        if (! Schema::hasTable('arbolaprobacion_nivel') || ! Schema::hasTable('arbolaprobacion')) {
            return;
        }

        $arbolIds = DB::table('arbolaprobacion')
            ->where('tipoarbol', 'Requisiciones')
            ->whereNull('deleted_at')
            ->pluck('id');

        if ($arbolIds->isEmpty()) {
            return;
        }

        DB::table('arbolaprobacion_nivel')
            ->whereIn('arbolaprobacion_id', $arbolIds)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('documento_estado_al_aprobar')
                    ->orWhere('documento_estado_al_aprobar', '');
            })
            ->update([
                'documento_estado_al_aprobar' => self::ESTADO_APROBADA,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // No se revierte: no es posible distinguir filas que ya eran APROBADA de las backfilleadas.
    }
};

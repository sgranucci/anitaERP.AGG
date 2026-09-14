<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completa backfill: articulo.estado legacy desde estado_fabrica / estado_local
 * cuando quedó NULL (filas históricas sin estado cargado).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('articulo')
            || ! Schema::hasColumn('articulo', 'estado_fabrica')
            || ! Schema::hasColumn('articulo', 'estado_local')) {
            return;
        }

        DB::table('articulo')
            ->where(function ($q) {
                $q->whereNull('estado')->orWhere('estado', '');
            })
            ->update([
                'estado' => DB::raw(
                    "CASE WHEN UPPER(COALESCE(estado_fabrica, '')) = 'ACTIVO'"
                    ." OR UPPER(COALESCE(estado_local, '')) = 'ACTIVO'"
                    ." THEN 'ACTIVO' ELSE 'INACTIVO' END"
                ),
            ]);
    }

    public function down(): void
    {
        // No revierte: el NULL previo no es recuperable de forma segura.
    }
};

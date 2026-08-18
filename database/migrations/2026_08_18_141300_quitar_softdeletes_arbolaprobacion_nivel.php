<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * SoftDeletes en niveles del árbol deja fantasmas que el circuito no ve
 * (incidente 18/ago/2026: nivel 261 Gastronomía REQUIS KANDIKO).
 * Purga las filas ya dadas de baja y quita deleted_at. La baja pasa a ser física + audits.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('arbolaprobacion_nivel')) {
            return;
        }

        if (Schema::hasColumn('arbolaprobacion_nivel', 'deleted_at')) {
            $bajas = (int) DB::table('arbolaprobacion_nivel')->whereNotNull('deleted_at')->count();
            DB::table('arbolaprobacion_nivel')->whereNotNull('deleted_at')->delete();
            Log::info('arbolaprobacion_nivel: purga soft-deletes', ['filas' => $bajas]);

            Schema::table('arbolaprobacion_nivel', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('arbolaprobacion_nivel')) {
            return;
        }

        if (! Schema::hasColumn('arbolaprobacion_nivel', 'deleted_at')) {
            Schema::table('arbolaprobacion_nivel', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }
};

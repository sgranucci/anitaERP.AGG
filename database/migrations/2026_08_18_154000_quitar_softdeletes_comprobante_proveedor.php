<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Comprobante proveedor: misma regla que el resto de procesos.
 * Baja física + audits. El unique fiscal no incluía deleted_at (un blando
 * bloqueaba recargar el mismo número); EliminarService ya hacía forceDelete.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('comprobante_proveedor') || ! Schema::hasColumn('comprobante_proveedor', 'deleted_at')) {
            return;
        }

        $bajas = (int) DB::table('comprobante_proveedor')->whereNotNull('deleted_at')->count();
        if ($bajas > 0) {
            DB::table('comprobante_proveedor')->whereNotNull('deleted_at')->delete();
            Log::info('softdeletes_cp: purga', ['filas' => $bajas]);
        }

        Schema::table('comprobante_proveedor', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('comprobante_proveedor') || Schema::hasColumn('comprobante_proveedor', 'deleted_at')) {
            return;
        }

        Schema::table('comprobante_proveedor', function (Blueprint $table) {
            $table->softDeletes();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RMV (Anita t_comp: Rendicion maquinas vending, estado interno)
 * + vínculo venta_id en cierre contable de rendición vending.
 */
return new class extends Migration
{
    public function up(): void
    {
        $existe = DB::table('tipotransaccion')
            ->where('abreviatura', 'RMV')
            ->whereNull('deleted_at')
            ->exists();

        if (! $existe) {
            DB::table('tipotransaccion')->insert([
                'nombre' => 'Rendicion maquinas vending',
                'operacion' => 'V',
                'operacionstock' => 'O',
                'abreviatura' => 'RMV',
                'codigo' => 'RMV',
                'signo' => 1,
                'estado' => 'A',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('rendicion_maquinavending_caja')
            && ! Schema::hasColumn('rendicion_maquinavending_caja', 'venta_id')) {
            Schema::table('rendicion_maquinavending_caja', function (Blueprint $table) {
                $table->unsignedBigInteger('venta_id')->nullable()->after('asiento_id');
                $table->foreign('venta_id')
                    ->references('id')
                    ->on('venta')
                    ->nullOnDelete();
                $table->index('venta_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rendicion_maquinavending_caja')
            && Schema::hasColumn('rendicion_maquinavending_caja', 'venta_id')) {
            Schema::table('rendicion_maquinavending_caja', function (Blueprint $table) {
                $table->dropForeign(['venta_id']);
                $table->dropIndex(['venta_id']);
                $table->dropColumn('venta_id');
            });
        }

        DB::table('tipotransaccion')
            ->where('abreviatura', 'RMV')
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now(), 'updated_at' => now()]);
    }
};

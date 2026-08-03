<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Fase 3/4 elegibilidad: vigencia (effective dating) + grupo OR.
 * Fase 2b: vínculo novedad ← ausencia para sync idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concepto_elegibilidad_sueldos', function (Blueprint $table) {
            $table->unsignedSmallInteger('grupo_or')->default(1)->after('valor');
            $table->date('vigente_desde')->nullable()->after('activo');
            $table->date('vigente_hasta')->nullable()->after('vigente_desde');
            $table->index(['concepto_id', 'grupo_or', 'activo'], 'conc_eleg_grupo_idx');
        });

        // Preservar AND histórico: cada regla queda en su propio grupo_or (1..N por concepto).
        $ids = DB::table('concepto_elegibilidad_sueldos')
            ->orderBy('concepto_id')
            ->orderBy('id')
            ->get(['id', 'concepto_id']);
        $porConcepto = [];
        foreach ($ids as $r) {
            $cid = (int) $r->concepto_id;
            $porConcepto[$cid] = ($porConcepto[$cid] ?? 0) + 1;
            DB::table('concepto_elegibilidad_sueldos')
                ->where('id', $r->id)
                ->update(['grupo_or' => $porConcepto[$cid]]);
        }

        if (! Schema::hasColumn('novedad_sueldos', 'ausencia_id')) {
            Schema::table('novedad_sueldos', function (Blueprint $table) {
                $table->unsignedBigInteger('ausencia_id')->nullable()->after('origen');
                $table->unique('ausencia_id', 'novedad_ausencia_uq');
                $table->foreign('ausencia_id')
                    ->references('id')
                    ->on('empleado_ausencia_sueldos')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('novedad_sueldos', 'ausencia_id')) {
            Schema::table('novedad_sueldos', function (Blueprint $table) {
                $table->dropForeign(['ausencia_id']);
                $table->dropUnique('novedad_ausencia_uq');
                $table->dropColumn('ausencia_id');
            });
        }

        Schema::table('concepto_elegibilidad_sueldos', function (Blueprint $table) {
            $table->dropIndex('conc_eleg_grupo_idx');
            $table->dropColumn(['grupo_or', 'vigente_desde', 'vigente_hasta']);
        });
    }
};

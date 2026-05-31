<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turno_operativo_gastronomia', function (Blueprint $table) {
            $table->unsignedInteger('numero_cierre')->nullable()->after('cierre_en');
            $table->unique(['empresa_id', 'numero_cierre'], 'uk_turno_operativo_empresa_numero_cierre');
        });

        $empresaIds = DB::table('turno_operativo_gastronomia')
            ->where('estado', 'cerrado')
            ->whereNotNull('cierre_en')
            ->distinct()
            ->pluck('empresa_id');

        foreach ($empresaIds as $empresaId) {
            $ids = DB::table('turno_operativo_gastronomia')
                ->where('empresa_id', $empresaId)
                ->where('estado', 'cerrado')
                ->whereNotNull('cierre_en')
                ->orderBy('cierre_en')
                ->orderBy('id')
                ->pluck('id');

            $n = 1;
            foreach ($ids as $id) {
                DB::table('turno_operativo_gastronomia')
                    ->where('id', $id)
                    ->update(['numero_cierre' => $n]);
                $n++;
            }
        }
    }

    public function down(): void
    {
        Schema::table('turno_operativo_gastronomia', function (Blueprint $table) {
            $table->dropUnique('uk_turno_operativo_empresa_numero_cierre');
            $table->dropColumn('numero_cierre');
        });
    }
};

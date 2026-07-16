<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SUSS: mínimo de retención (RG 1784 $400) + régimen general 1% faltante en catálogo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('retencionsuss', 'minimoretencion')) {
            Schema::table('retencionsuss', function (Blueprint $table) {
                $table->decimal('minimoretencion', 22, 4)->default(0)->after('valorretencion');
            });
        }

        $now = now();

        if (! DB::table('retencionsuss')->where('regimen', '755')->exists()) {
            DB::table('retencionsuss')->insert([
                'nombre' => 'R.G. 1784 Régimen general',
                'codigo' => '10',
                'regimen' => '755',
                'formacalculo' => 'P',
                'minimoimponible' => 0,
                'valorretencion' => 1,
                'minimoretencion' => 400,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('retencionsuss')
                ->where('regimen', '755')
                ->where(function ($q) {
                    $q->whereNull('minimoretencion')->orWhere('minimoretencion', 0);
                })
                ->update([
                    'minimoretencion' => 400,
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        DB::table('retencionsuss')
            ->where('regimen', '755')
            ->where('codigo', '10')
            ->where('nombre', 'R.G. 1784 Régimen general')
            ->delete();

        if (Schema::hasColumn('retencionsuss', 'minimoretencion')) {
            Schema::table('retencionsuss', function (Blueprint $table) {
                $table->dropColumn('minimoretencion');
            });
        }
    }
};

<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Una sola marca de artículo Waitry: preparar o no preparar. Solo instalación AGG.
 * Copia va a cocina, así un artículo que no iba a cocina queda como no preparar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        if (! Schema::hasTable('articulo') || ! Schema::hasColumn('articulo', 'waitry_va_cocina')) {
            return;
        }

        if (! Schema::hasColumn('articulo', 'waitry_preparar')) {
            Schema::table('articulo', function (Blueprint $table) {
                $table->boolean('waitry_preparar')->default(true)->after('waitry_va_cocina');
            });
        }

        DB::table('articulo')->update([
            'waitry_preparar' => DB::raw('waitry_va_cocina'),
        ]);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        if (Schema::hasTable('articulo') && Schema::hasColumn('articulo', 'waitry_preparar')) {
            Schema::table('articulo', function (Blueprint $table) {
                $table->dropColumn('waitry_preparar');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Código condmae.conm_codigo (Informix) para condición de venta en el ERP.
     */
    public function up(): void
    {
        if (! Schema::hasTable('condicionventa')) {
            return;
        }
        if (Schema::hasColumn('condicionventa', 'codigo')) {
            return;
        }

        Schema::table('condicionventa', function (Blueprint $table) {
            $table->string('codigo', 20)->nullable()->after('nombre');
        });

        DB::table('condicionventa')
            ->whereNull('codigo')
            ->update(['codigo' => DB::raw('CAST(id AS CHAR)')]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('condicionventa')) {
            return;
        }
        if (! Schema::hasColumn('condicionventa', 'codigo')) {
            return;
        }

        Schema::table('condicionventa', function (Blueprint $table) {
            $table->dropColumn('codigo');
        });
    }
};

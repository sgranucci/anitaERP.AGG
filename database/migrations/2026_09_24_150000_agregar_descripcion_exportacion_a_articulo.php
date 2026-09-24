<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Descripción de exportación (Anita stkley línea 100). Distinta de detalle / descripción corta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('articulo', 'descripcion_exportacion')) {
            Schema::table('articulo', function (Blueprint $table) {
                $table->string('descripcion_exportacion', 255)->nullable()->after('detalle');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('articulo', 'descripcion_exportacion')) {
            Schema::table('articulo', function (Blueprint $table) {
                $table->dropColumn('descripcion_exportacion');
            });
        }
    }
};

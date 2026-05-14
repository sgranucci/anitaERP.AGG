<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('requisicion_articulo', 'precio_origen_etiqueta')) {
            Schema::table('requisicion_articulo', function (Blueprint $table) {
                $table->string('precio_origen_etiqueta', 512)->nullable()->after('capex_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('requisicion_articulo', 'precio_origen_etiqueta')) {
            Schema::table('requisicion_articulo', function (Blueprint $table) {
                $table->dropColumn('precio_origen_etiqueta');
            });
        }
    }
};

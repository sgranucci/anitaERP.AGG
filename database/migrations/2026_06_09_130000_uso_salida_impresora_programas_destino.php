<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uso_salida_impresora', function (Blueprint $table) {
            $table->json('programas_destino')->nullable()->after('descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('uso_salida_impresora', function (Blueprint $table) {
            $table->dropColumn('programas_destino');
        });
    }
};

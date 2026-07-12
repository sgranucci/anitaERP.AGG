<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cliente', function (Blueprint $table) {
            $table->boolean('facturas_apocrifas')->nullable()->after('estado');
            $table->timestamp('facturas_apocrifas_consulta_at')->nullable()->after('facturas_apocrifas');
            $table->text('facturas_apocrifas_detalle')->nullable()->after('facturas_apocrifas_consulta_at');
        });
    }

    public function down(): void
    {
        Schema::table('cliente', function (Blueprint $table) {
            $table->dropColumn([
                'facturas_apocrifas',
                'facturas_apocrifas_consulta_at',
                'facturas_apocrifas_detalle',
            ]);
        });
    }
};

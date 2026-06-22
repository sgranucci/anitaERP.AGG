<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('precarga_comprobante_proveedor', function (Blueprint $table) {
            $table->string('origen_entrada', 20)->nullable()->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('precarga_comprobante_proveedor', function (Blueprint $table) {
            $table->dropColumn('origen_entrada');
        });
    }
};

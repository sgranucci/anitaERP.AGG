<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('precarga_comprobante_proveedor', function (Blueprint $table) {
            $table->date('fechavencimientocaicae')->nullable()->change();
            $table->string('numerocae', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('precarga_comprobante_proveedor', function (Blueprint $table) {
            $table->date('fechavencimientocaicae')->nullable(false)->change();
            $table->string('numerocae', 50)->nullable(false)->change();
        });
    }
};

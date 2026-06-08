<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
            $table->unsignedBigInteger('impuesto_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
            $table->unsignedBigInteger('impuesto_id')->nullable(false)->change();
        });
    }
};

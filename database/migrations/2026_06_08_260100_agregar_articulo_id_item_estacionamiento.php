<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_estacionamiento', function (Blueprint $table) {
            $table->unsignedBigInteger('articulo_id')->nullable()->after('empresa_id');
            $table->foreign('articulo_id', 'fk_item_estacionamiento_articulo')
                ->references('id')->on('articulo')
                ->onDelete('set null')
                ->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('item_estacionamiento', function (Blueprint $table) {
            $table->dropForeign('fk_item_estacionamiento_articulo');
            $table->dropColumn('articulo_id');
        });
    }
};

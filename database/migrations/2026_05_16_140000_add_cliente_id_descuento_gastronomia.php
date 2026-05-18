<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('descuento_gastronomia', function (Blueprint $table) {
            $table->unsignedBigInteger('cliente_id')->nullable()->after('valor');
            $table->foreign('cliente_id', 'fk_descuento_gastro_cliente')
                ->references('id')->on('cliente')
                ->onDelete('set null')
                ->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('descuento_gastronomia', function (Blueprint $table) {
            $table->dropForeign('fk_descuento_gastro_cliente');
            $table->dropColumn('cliente_id');
        });
    }
};

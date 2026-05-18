<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuenta_gastronomia', function (Blueprint $table) {
            $table->unsignedBigInteger('cliente_interno_descuento_id')->nullable()->after('descuento_gastronomia_id');
            $table->foreign('cliente_interno_descuento_id', 'fk_cuenta_gastro_cli_interno_desc')
                ->references('id')->on('cliente')
                ->onDelete('set null')
                ->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('cuenta_gastronomia', function (Blueprint $table) {
            $table->dropForeign('fk_cuenta_gastro_cli_interno_desc');
            $table->dropColumn('cliente_interno_descuento_id');
        });
    }
};

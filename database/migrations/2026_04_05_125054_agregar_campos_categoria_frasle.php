<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categoria', function (Blueprint $table) {
            $table->unsignedBigInteger('division')->after('tipoarticulo_id')->nullable();
            $table->string('estado',50)->after('division');
            $table->unsignedBigInteger('grupocompra')->after('estado')->nullable();
            $table->unsignedBigInteger('linea_id')->after('grupocompra')->nullable();
            $table->foreign('linea_id', 'fk_categoria_linea')->references('id')->on('linea')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('deposito_id')->after('linea_id')->nullable();
            $table->foreign('deposito_id', 'fk_categoria_depmae')->references('id')->on('depmae')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('puntoventa_id')->after('deposito_id')->nullable();
            $table->foreign('puntoventa_id', 'fk_categoria_puntoventa')->references('id')->on('puntoventa')->onDelete('restrict')->onUpdate('restrict');
            $table->string('excel',255)->after('puntoventa_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categoria', function (Blueprint $table) {
            $table->dropColumn('excel');
            $table->dropForeign('fk_categoria_puntoventa');
            $table->dropColumn('puntoventa_id');
            $table->dropForeign('fk_categoria_deposito');
            $table->dropColumn('deposito_id');
            $table->dropForeign('fk_categoria_linea');
            $table->dropColumn('linea_id');
            $table->dropColumn('grupocom_id');
            $table->dropColumn('estado');
            $table->dropColumn('division_id');
        });
    }
};

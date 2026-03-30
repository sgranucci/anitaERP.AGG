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
        Schema::table('proveedor', function (Blueprint $table) {
            $table->string('semaforo', 50)->after('tipoalta')->nullable();
            $table->string('emailoc', 255)->after('semaforo')->nullable();
            $table->string('regimenfacturacion', 50)->after('emailoc');
            $table->unsignedBigInteger('tiposervicio_proveedor_id')->after('regimenfacturacion')->nullable();
            $table->foreign('tiposervicio_proveedor_id', 'fk_proveedor_tiposervicion_proveedor')->references('id')->on('tiposervicio_proveedor')->onDelete('restrict')->onUpdate('restrict');
            $table->string('estado', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proveedor', function (Blueprint $table) {
            $table->dropColumn('emailoc');
            $table->dropColumn('semaforo');
            $table->dropColumn('regimenfacturacion');
            $table->dropForeign('fk_proveedor_tiposervicion_proveedor');
            $table->dropColumn('tiposervicio_proveedor_id');
            $table->string('estado', 1)->change();
        });
    }
};

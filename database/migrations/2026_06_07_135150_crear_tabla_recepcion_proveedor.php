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
        Schema::create('recepcion_proveedor', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ordencompra_id');
            $table->foreign('ordencompra_id', 'fk_recepcion_proveedor_ordencompra')->references('id')->on('ordencompra')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_recepcion_proveedor_empresa')->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('proveedor_id');
            $table->foreign('proveedor_id', 'fk_recepcion_proveedor_proveedor')->references('id')->on('proveedor')->onDelete('restrict')->onUpdate('restrict');
            $table->date('fecha');
            $table->string('numerorecepcion', 50);
            $table->string('numerofactura', 50);
            $table->string('estado', 50);
            $table->string('observacion', 255)->nullable();
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_recepcion_proveedor_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recepcion_proveedor');
    }
};

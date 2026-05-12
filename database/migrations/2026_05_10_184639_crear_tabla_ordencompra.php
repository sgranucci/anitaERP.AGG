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
        Schema::create('ordencompra', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('fecha');
            $table->date('fechaentrega');
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_ordencompra_empresa')->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('numeroordencompra');
            $table->unsignedBigInteger('requisicion_id')->nullable();
            $table->foreign('requisicion_id', 'fk_ordencompra_requisicion')->references('id')->on('requisicion')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('centrocosto_id');
            $table->foreign('centrocosto_id', 'fk_ordencompra_centrocosto')->references('id')->on('centrocosto')->onDelete('restrict')->onUpdate('restrict');
            $table->string('comentario', 255);
            $table->text('detalle');
            $table->string('lugarentrega', 255)->nullable();
            $table->unsignedBigInteger('transporte_id')->nullable();
            $table->foreign('transporte_id', 'fk_ordencompra_transporte')->references('id')->on('transporte')->onDelete('set null')->onUpdate('set null');
            $table->string('tratamiento', 50);
            $table->unsignedBigInteger('proveedor_id')->nullable();
            $table->foreign('proveedor_id', 'fk_ordencompra_proveedor')->references('id')->on('proveedor')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('condicioncompra_id')->nullable();
            $table->foreign('condicioncompra_id', 'fk_ordencompra_condicioncompra')->references('id')->on('condicioncompra')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('condicionentrega_id')->nullable();
            $table->foreign('condicionentrega_id', 'fk_ordencompra_condicionentrega')->references('id')->on('condicionentrega')->onDelete('set null')->onUpdate('set null');
            $table->decimal('descuento', 5, 2)->nullable();
            $table->string('estadoordencompra', 50)->nullable();
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_ordencompra_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('ordencompra');
    }
};

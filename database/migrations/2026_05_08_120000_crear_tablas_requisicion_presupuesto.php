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
        Schema::create('requisicion_presupuesto', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('requisicion_id');
            $table->foreign('requisicion_id', 'fk_req_presupuesto_requisicion')->references('id')->on('requisicion')->onDelete('cascade')->onUpdate('cascade');
            $table->date('fecha');
            $table->text('condiciones_entrega')->nullable();
            $table->text('condiciones_compra')->nullable();
            $table->text('condiciones_pago')->nullable();
            $table->unsignedBigInteger('proveedor_id');
            $table->foreign('proveedor_id', 'fk_req_presupuesto_proveedor')->references('id')->on('proveedor')->onDelete('restrict')->onUpdate('restrict');
            $table->string('estado', 50);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });

        Schema::create('requisicion_presupuesto_articulo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('requisicion_presupuesto_id');
            $table->foreign('requisicion_presupuesto_id', 'fk_req_pres_art_pres')->references('id')->on('requisicion_presupuesto')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('requisicion_articulo_id');
            $table->foreign('requisicion_articulo_id', 'fk_req_pres_art_req_art')->references('id')->on('requisicion_articulo')->onDelete('cascade')->onUpdate('cascade');
            $table->decimal('precio_unitario', 22, 4);
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });

        Schema::create('requisicion_presupuesto_archivo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('requisicion_presupuesto_id');
            $table->foreign('requisicion_presupuesto_id', 'fk_req_pres_arch_pres')->references('id')->on('requisicion_presupuesto')->onDelete('cascade')->onUpdate('cascade');
            $table->string('nombrearchivo', 255);
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
        Schema::dropIfExists('requisicion_presupuesto_archivo');
        Schema::dropIfExists('requisicion_presupuesto_articulo');
        Schema::dropIfExists('requisicion_presupuesto');
    }
};

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
        if (Schema::hasTable('requisicion_sala')) {
            return;
        }

        Schema::create('requisicion_sala', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('fecha');
            $table->date('fecha_entrega');
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_requisicion_sala_empresa')->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('numerorequisicion');
            $table->unsignedBigInteger('deposito_id');
            $table->foreign('deposito_id', 'fk_requisicion_sala_deposito')->references('id')->on('depmae')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('centrocosto_id');
            $table->foreign('centrocosto_id', 'fk_requisicion_sala_centrocosto')->references('id')->on('centrocosto')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('usuario_id');
            $table->foreign('usuario_id', 'fk_requisicion_sala_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
            $table->string('estado', 50)->nullable();
            $table->string('comentario',255)->nullable();
            $table->text('detalle')->nullable();
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_requisicion_sala_creousuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('requisicion_sala');
    }
};

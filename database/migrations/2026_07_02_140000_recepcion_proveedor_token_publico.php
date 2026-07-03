<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tokens de consulta pública para enlaces de mail de recepción de proveedor (sin login ERP).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('recepcion_proveedor_token')) {
            return;
        }

        Schema::create('recepcion_proveedor_token', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('recepcion_proveedor_id');
            $table->string('token', 80)->unique();
            $table->string('accion', 20)->default('visualizar');
            $table->unsignedBigInteger('usuario_destino_id')->nullable();
            $table->timestamp('usado_el')->nullable();
            $table->timestamp('expira_el')->nullable();
            $table->timestamps();

            $table->foreign('recepcion_proveedor_id', 'fk_recepcionprovtoken_recepcion')
                ->references('id')->on('recepcion_proveedor')
                ->onDelete('cascade')->onUpdate('restrict');
            $table->foreign('usuario_destino_id', 'fk_recepcionprovtoken_usuario')
                ->references('id')->on('usuario')
                ->onDelete('set null')->onUpdate('restrict');

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recepcion_proveedor_token');
    }
};

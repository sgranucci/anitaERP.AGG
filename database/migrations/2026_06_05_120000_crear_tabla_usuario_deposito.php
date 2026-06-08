<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Depósitos de stock que un usuario puede operar (opcional por usuario).
 * Sin filas = sin restricción (solo filtro por empresa asignada).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('usuario_deposito')) {
            return;
        }

        Schema::create('usuario_deposito', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('deposito_id');

            $table->foreign('usuario_id', 'fk_usuario_deposito_usuario')
                ->references('id')->on('usuario')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('deposito_id', 'fk_usuario_deposito_depmae')
                ->references('id')->on('depmae')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->unique(['usuario_id', 'deposito_id'], 'uk_usuario_deposito');
            $table->index('deposito_id', 'ix_usuario_deposito_deposito');

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_deposito');
    }
};

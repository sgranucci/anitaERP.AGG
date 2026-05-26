<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asignación de usuarios administradores/encargados por depósito.
 * Cada depósito puede tener uno o varios administradores: a ellos se
 * les enviarán los correos de aprobación/recordatorio de préstamos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('deposito_administrador')) {
            return;
        }

        Schema::create('deposito_administrador', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('deposito_id');
            $table->unsignedBigInteger('usuario_id');
            $table->boolean('principal')->default(false);
            $table->boolean('recibe_avisos')->default(true);
            $table->boolean('aprueba_recepcion')->default(true);
            $table->timestamps();

            $table->foreign('deposito_id', 'fk_depadm_depmae')
                ->references('id')->on('depmae')
                ->onDelete('cascade')->onUpdate('restrict');
            $table->foreign('usuario_id', 'fk_depadm_usuario')
                ->references('id')->on('usuario')
                ->onDelete('cascade')->onUpdate('restrict');

            $table->unique(['deposito_id', 'usuario_id'], 'uk_depadm_deposito_usuario');
            $table->index('usuario_id', 'ix_depadm_usuario');

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposito_administrador');
    }
};

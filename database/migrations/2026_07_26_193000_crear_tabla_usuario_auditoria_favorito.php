<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modelos auditables marcados como favoritos por usuario (panel auditoría datos).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('usuario_auditoria_favorito')) {
            return;
        }

        Schema::create('usuario_auditoria_favorito', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('usuario_id');
            $table->string('auditable_type', 191);
            $table->string('etiqueta', 120)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);

            $table->foreign('usuario_id', 'fk_usuario_auditoria_favorito_usuario')
                ->references('id')->on('usuario')
                ->onDelete('cascade')->onUpdate('cascade');

            $table->unique(['usuario_id', 'auditable_type'], 'uk_usuario_auditoria_favorito');
            $table->index(['usuario_id', 'orden'], 'ix_usuario_auditoria_favorito_orden');

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_auditoria_favorito');
    }
};

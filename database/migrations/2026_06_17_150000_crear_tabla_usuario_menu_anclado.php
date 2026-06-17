<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programas anclados por usuario en la barra de tareas del footer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('usuario_menu_anclado')) {
            return;
        }

        Schema::create('usuario_menu_anclado', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('menu_id');
            $table->unsignedSmallInteger('orden')->default(0);

            $table->foreign('usuario_id', 'fk_usuario_menu_anclado_usuario')
                ->references('id')->on('usuario')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('menu_id', 'fk_usuario_menu_anclado_menu')
                ->references('id')->on('menu')
                ->onDelete('cascade')->onUpdate('cascade');

            $table->unique(['usuario_id', 'menu_id'], 'uk_usuario_menu_anclado');
            $table->index(['usuario_id', 'orden'], 'ix_usuario_menu_anclado_orden');

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_menu_anclado');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vianda_usuario', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('codigo_usuario');
            $table->string('nombre', 255);
            $table->string('password', 15);
            $table->unsignedBigInteger('centrocosto_id')->nullable();
            $table->char('tipo_usuario', 1);
            $table->unsignedBigInteger('vianda_tipo_menu_id')->nullable();
            $table->char('estado', 1)->default('A');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->unique('codigo_usuario', 'uq_vianda_usuario_codigo');

            $table->foreign('centrocosto_id', 'fk_vianda_usuario_centrocosto')
                ->references('id')->on('centrocosto')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->foreign('vianda_tipo_menu_id', 'fk_vianda_usuario_tipo_menu')
                ->references('id')->on('vianda_tipo_menu')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->index(['estado', 'nombre'], 'idx_vianda_usuario_estado_nombre');
            $table->index('tipo_usuario', 'idx_vianda_usuario_tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vianda_usuario');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vianda_tipo_menu', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('codigo_anita')->nullable();
            $table->string('nombre', 255);
            $table->char('estado', 1)->default('A');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->unique('codigo_anita', 'uq_vianda_tipo_menu_codigo_anita');
            $table->index('estado', 'idx_vianda_tipo_menu_estado');
        });

        Schema::create('vianda_tipo_menu_articulo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('vianda_tipo_menu_id');
            $table->unsignedTinyInteger('dia_semana');
            $table->unsignedBigInteger('articulo_id');
            $table->unsignedSmallInteger('orden')->default(1);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->foreign('vianda_tipo_menu_id', 'fk_vianda_tipo_menu_articulo_tipo')
                ->references('id')->on('vianda_tipo_menu')
                ->onDelete('cascade')->onUpdate('restrict');

            $table->foreign('articulo_id', 'fk_vianda_tipo_menu_articulo_articulo')
                ->references('id')->on('articulo')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->index(['vianda_tipo_menu_id', 'dia_semana', 'orden'], 'idx_vianda_tipo_menu_art_dia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vianda_tipo_menu_articulo');
        Schema::dropIfExists('vianda_tipo_menu');
    }
};

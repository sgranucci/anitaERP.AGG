<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widgets del tablero personal de Consulta Inteligente (persistidos por usuario).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('consulta_inteligente_tablero_widget')) {
            return;
        }

        Schema::create('consulta_inteligente_tablero_widget', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id')->index();
            $table->string('entidad', 80);
            $table->string('titulo', 80);
            $table->string('tipo', 40)->default('atajo');
            $table->json('query_json')->nullable();
            $table->unsignedTinyInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['usuario_id', 'orden'], 'ci_tablero_widget_usuario_orden_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consulta_inteligente_tablero_widget');
    }
};

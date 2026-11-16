<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Favoritos de preguntas NL en Consulta Inteligente (por usuario).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('consulta_inteligente_nl_favorito')) {
            return;
        }

        Schema::create('consulta_inteligente_nl_favorito', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id')->index();
            $table->string('titulo', 80);
            $table->string('pregunta', 500);
            $table->unsignedTinyInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['usuario_id', 'orden'], 'ci_nl_favorito_usuario_orden_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consulta_inteligente_nl_favorito');
    }
};

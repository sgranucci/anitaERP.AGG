<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enlaces compartibles de preguntas NL (Consulta Inteligente).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('consulta_inteligente_nl_enlace')) {
            return;
        }

        Schema::create('consulta_inteligente_nl_enlace', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->unsignedBigInteger('usuario_id')->index();
            $table->string('titulo', 80);
            $table->string('pregunta', 500);
            $table->string('entidad', 64)->nullable();
            $table->json('query_json')->nullable();
            $table->timestamps();

            $table->index(['usuario_id', 'created_at'], 'ci_nl_enlace_usuario_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consulta_inteligente_nl_enlace');
    }
};

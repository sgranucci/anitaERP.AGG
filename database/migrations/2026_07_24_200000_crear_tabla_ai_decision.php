<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_decision', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('skill', 80);
            $table->string('accion', 30)->default('sugerida');
            $table->string('driver', 30)->nullable();
            $table->string('model', 80)->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('entidad_tipo', 80)->nullable();
            $table->unsignedBigInteger('entidad_id')->nullable();
            $table->decimal('score', 5, 4)->nullable();
            $table->unsignedInteger('latencia_ms')->nullable();
            $table->string('input_hash', 64)->nullable();
            $table->json('payload')->nullable();
            $table->unsignedBigInteger('resuelto_por')->nullable();
            $table->timestamp('resuelto_at')->nullable();
            $table->timestamps();

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index(['skill', 'accion'], 'idx_ai_decision_skill_accion');
            $table->index(['entidad_tipo', 'entidad_id'], 'idx_ai_decision_entidad');
            $table->index('empresa_id', 'idx_ai_decision_empresa');
            $table->index('created_at', 'idx_ai_decision_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_decision');
    }
};

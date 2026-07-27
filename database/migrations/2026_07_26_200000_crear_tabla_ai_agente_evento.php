<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Puente auditores determinísticos → plan HITL de IA (Fase C agentic).
 * No reemplaza los agentes de auditoría; solo registra el evento y el plan propuesto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agente_evento', function (Blueprint $table) {
            $table->id();
            $table->string('evento', 64)->index();
            $table->string('origen', 120)->index();
            $table->string('severidad', 16)->default('media')->index();
            $table->string('estado', 24)->default('pendiente')->index();
            $table->string('entidad_tipo', 64)->nullable()->index();
            $table->unsignedBigInteger('entidad_id')->nullable()->index();
            $table->unsignedBigInteger('empresa_id')->nullable()->index();
            $table->string('resumen', 500);
            $table->json('payload_json')->nullable();
            $table->json('plan_json')->nullable();
            $table->unsignedBigInteger('ai_decision_id')->nullable()->index();
            $table->timestamp('visto_at')->nullable();
            $table->timestamp('resuelto_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agente_evento');
    }
};

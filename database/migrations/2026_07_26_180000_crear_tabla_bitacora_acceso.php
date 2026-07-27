<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de navegación / sesión (1 fila por request relevante).
 * Escritura post-respuesta; sin cola Laravel.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bitacora_acceso')) {
            return;
        }

        Schema::create('bitacora_acceso', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('usuario_id')->nullable()->index();
            $table->string('usuario_nombre', 120)->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable()->index();
            $table->unsignedBigInteger('rol_id')->nullable();
            $table->string('session_id', 64)->nullable()->index();
            $table->string('tipo', 20)->default('navegacion')->index(); // navegacion|login|logout
            $table->string('metodo', 10);
            $table->string('ruta', 255)->nullable();
            $table->string('nombre_ruta', 120)->nullable()->index();
            $table->string('url', 500)->nullable();
            $table->unsignedSmallInteger('status')->nullable();
            $table->unsignedInteger('duracion_ms')->nullable();
            $table->unsignedInteger('memoria_pico_kb')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['usuario_id', 'created_at']);
            $table->index(['tipo', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora_acceso');
    }
};

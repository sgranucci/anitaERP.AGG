<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diccionario NL, snapshots y auditoría de Consulta Inteligente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('consulta_inteligente_nl_sinonimo')) {
            Schema::create('consulta_inteligente_nl_sinonimo', function (Blueprint $table) {
                $table->id();
                $table->string('frase', 120);
                $table->string('entidad', 80);
                $table->unsignedSmallInteger('prioridad')->default(100);
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->unique(['frase', 'entidad'], 'ci_nl_sinonimo_frase_entidad_uq');
                $table->index(['activo', 'prioridad'], 'ci_nl_sinonimo_activo_prio_idx');
            });
        }

        if (! Schema::hasTable('consulta_inteligente_snapshot')) {
            Schema::create('consulta_inteligente_snapshot', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('usuario_id')->index();
                $table->string('entidad', 80);
                $table->string('titulo', 120);
                $table->json('filtros_json')->nullable();
                $table->json('columnas_json')->nullable();
                $table->json('filas_json');
                $table->unsignedInteger('filas_count')->default(0);
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();

                $table->index(['usuario_id', 'entidad'], 'ci_snapshot_usuario_entidad_idx');
            });
        }

        if (! Schema::hasTable('consulta_inteligente_auditoria')) {
            Schema::create('consulta_inteligente_auditoria', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('usuario_id')->nullable()->index();
                $table->string('accion', 40);
                $table->string('entidad', 80)->nullable()->index();
                $table->string('detalle', 255)->nullable();
                $table->json('meta_json')->nullable();
                $table->timestamp('created_at')->useCurrent()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('consulta_inteligente_auditoria');
        Schema::dropIfExists('consulta_inteligente_snapshot');
        Schema::dropIfExists('consulta_inteligente_nl_sinonimo');
    }
};

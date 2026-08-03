<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restaura rendicion_maquina_ajuste_wigos (borrada por error).
 * El campo UI «ajuste_wigosd» no vuelve: esta tabla es el log de ajustes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rendicion_maquina_ajuste_wigos')) {
            return;
        }

        Schema::create('rendicion_maquina_ajuste_wigos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('rendicion_maquina_id')->nullable();
            $table->unsignedBigInteger('empresa_id');
            $table->date('fecha');
            $table->string('turno', 1);
            $table->unsignedInteger('nro_oper')->nullable();
            $table->string('campo', 80);
            $table->string('etiqueta', 120)->nullable();
            $table->decimal('valor_wigos', 18, 2);
            $table->decimal('valor_ajustado', 18, 2);
            $table->decimal('delta', 18, 2);
            $table->string('motivo', 500)->nullable();
            $table->unsignedBigInteger('usuario_id');
            $table->timestamps();

            $table->foreign('empresa_id', 'fk_rendmaq_ajw_empresa')
                ->references('id')->on('empresa')->restrictOnDelete();
            $table->foreign('usuario_id', 'fk_rendmaq_ajw_usuario')
                ->references('id')->on('usuario')->restrictOnDelete();

            $table->index(['empresa_id', 'fecha', 'turno'], 'idx_rendmaq_ajw_emp_fecha_turno');
            $table->index(['rendicion_maquina_id'], 'idx_rendmaq_ajw_rendicion');
            $table->index(['campo'], 'idx_rendmaq_ajw_campo');
        });

        if (Schema::hasTable('rendicion_maquina')) {
            try {
                Schema::table('rendicion_maquina_ajuste_wigos', function (Blueprint $table) {
                    $table->foreign('rendicion_maquina_id', 'fk_rendmaq_ajw_rendicion')
                        ->references('id')->on('rendicion_maquina')->nullOnDelete();
                });
            } catch (\Throwable) {
                // FK ya existente o re-run
            }
        }
    }

    public function down(): void
    {
        // No borrar: la tabla es parte del módulo.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contable_periodo_cierre')) {
            Schema::create('contable_periodo_cierre', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('empresa_id');
                $table->date('fecha_hasta')->comment('Última fecha incluida en el cierre; operaciones con fecha anterior o igual quedan bloqueadas');
                $table->text('observacion')->nullable();
                $table->unsignedInteger('usuario_id');
                $table->timestamps();

                $table->index(['empresa_id', 'fecha_hasta']);
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }

        if (! Schema::hasTable('contable_apertura_periodo')) {
            Schema::create('contable_apertura_periodo', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('empresa_id');
                $table->unsignedInteger('usuario_solicitante_id');
                $table->unsignedInteger('usuario_habilitado_id');
                $table->unsignedInteger('usuario_aprobador_id')->nullable();
                $table->date('fecha_operacion_desde');
                $table->date('fecha_operacion_hasta');
                $table->string('alcance', 32)->comment('general|cobranza|caja|transferencia|stock|contable|facturacion');
                $table->unsignedSmallInteger('duracion_cantidad');
                $table->string('duracion_unidad', 8)->comment('horas|dias');
                $table->dateTime('inicio_en')->nullable();
                $table->dateTime('vence_en')->nullable();
                $table->string('estado', 16)->default('pendiente')->comment('pendiente|activa|vencida|revocada|rechazada');
                $table->text('motivo');
                $table->text('observacion_aprobacion')->nullable();
                $table->dateTime('aviso_habilitacion_enviado_en')->nullable();
                $table->dateTime('recordatorio_vencimiento_enviado_en')->nullable();
                $table->dateTime('aviso_cierre_enviado_en')->nullable();
                $table->timestamps();

                $table->index(['empresa_id', 'estado', 'vence_en']);
                $table->index(['usuario_habilitado_id', 'estado']);
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contable_apertura_periodo');
        Schema::dropIfExists('contable_periodo_cierre');
    }
};

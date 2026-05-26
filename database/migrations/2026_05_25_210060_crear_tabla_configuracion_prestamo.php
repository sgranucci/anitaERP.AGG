<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración global del módulo de préstamos: asuntos / cuerpos de los
 * mails y reglas de envío de recordatorios. Una sola fila (id = 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configuracion_prestamo')) {
            Schema::create('configuracion_prestamo', function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->boolean('enviar_aprobacion')->default(true);
                $table->boolean('enviar_recordatorios')->default(true);
                $table->integer('dias_antes_devolucion_aviso')->default(2);
                $table->integer('dias_repeticion_vencido')->default(3);
                $table->integer('horas_validez_token')->default(168)
                    ->comment('Vigencia de los enlaces aprobar/rechazar enviados por mail (horas).');

                $table->string('mail_asunto_aprobacion', 255)
                    ->default('Préstamo de materiales: pendiente de aprobación');
                $table->string('mail_asunto_recordatorio', 255)
                    ->default('Recordatorio de devolución de préstamo');
                $table->string('mail_asunto_devolucion_vencida', 255)
                    ->default('Préstamo vencido — devolución pendiente');
                $table->string('mail_asunto_aprobado_solicitante', 255)
                    ->default('Préstamo aprobado por el destinatario');
                $table->string('mail_asunto_rechazado_solicitante', 255)
                    ->default('Préstamo rechazado por el destinatario');

                $table->string('mail_remitente', 255)->nullable();
                $table->string('mail_copia_a', 255)->nullable()
                    ->comment('Cuentas de copia separadas por coma (CC)');

                $table->text('mail_texto_aprobacion')->nullable();
                $table->text('mail_texto_recordatorio')->nullable();
                $table->text('mail_texto_devolucion_vencida')->nullable();

                $table->timestamps();

                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }

        if (DB::table('configuracion_prestamo')->count() === 0) {
            DB::table('configuracion_prestamo')->insert([
                'enviar_aprobacion' => true,
                'enviar_recordatorios' => true,
                'dias_antes_devolucion_aviso' => 2,
                'dias_repeticion_vencido' => 3,
                'horas_validez_token' => 168,
                'mail_asunto_aprobacion' => 'Préstamo de materiales: pendiente de aprobación',
                'mail_asunto_recordatorio' => 'Recordatorio de devolución de préstamo',
                'mail_asunto_devolucion_vencida' => 'Préstamo vencido — devolución pendiente',
                'mail_asunto_aprobado_solicitante' => 'Préstamo aprobado por el destinatario',
                'mail_asunto_rechazado_solicitante' => 'Préstamo rechazado por el destinatario',
                'mail_texto_aprobacion' => 'Recibirás un préstamo de materiales. Por favor revisalo y aprobalo o rechazalo desde los enlaces de este correo.',
                'mail_texto_recordatorio' => 'Te recordamos que tenés materiales pendientes de devolución del préstamo arriba detallado.',
                'mail_texto_devolucion_vencida' => 'El préstamo arriba detallado venció su fecha de devolución prometida. Por favor contactá al solicitante.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_prestamo');
    }
};

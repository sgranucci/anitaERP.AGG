<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aprobación de asientos con cuentas no autorizadas para el usuario.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asiento') && ! Schema::hasColumn('asiento', 'estado_aprobacion')) {
            Schema::table('asiento', function (Blueprint $table) {
                $table->string('estado_aprobacion', 20)->default('confirmado')
                    ->after('usuario_id')
                    ->comment('confirmado | pendiente | rechazado');
                $table->text('cuentas_no_autorizadas')->nullable()
                    ->after('estado_aprobacion')
                    ->comment('JSON con ids de cuentas que dispararon la aprobación');
                $table->unsignedBigInteger('aprobador_id')->nullable()->after('cuentas_no_autorizadas');
                $table->timestamp('aprobado_el')->nullable()->after('aprobador_id');
                $table->timestamp('rechazado_el')->nullable()->after('aprobado_el');
                $table->text('motivo_rechazo')->nullable()->after('rechazado_el');

                $table->foreign('aprobador_id', 'fk_asiento_aprobador')
                    ->references('id')->on('usuario')
                    ->onDelete('set null')->onUpdate('restrict');
            });
        }

        if (! Schema::hasTable('asiento_token')) {
            Schema::create('asiento_token', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('asiento_id');
                $table->string('token', 80)->unique();
                $table->string('accion', 20)->comment('aprobar | rechazar | visualizar');
                $table->unsignedBigInteger('usuario_destino_id')->nullable();
                $table->timestamp('usado_el')->nullable();
                $table->timestamp('expira_el')->nullable();
                $table->timestamps();

                $table->foreign('asiento_id', 'fk_asientotoken_asiento')
                    ->references('id')->on('asiento')
                    ->onDelete('cascade')->onUpdate('restrict');
                $table->foreign('usuario_destino_id', 'fk_asientotoken_usuario')
                    ->references('id')->on('usuario')
                    ->onDelete('set null')->onUpdate('restrict');

                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }

        if (! Schema::hasTable('configuracion_asiento_contable')) {
            Schema::create('configuracion_asiento_contable', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->boolean('enviar_mail_aprobacion')->default(true);
                $table->string('mail_aprobador', 255)->nullable()
                    ->comment('Correo del responsable de contaduría que aprueba asientos');
                $table->string('mail_copia_a', 255)->nullable()
                    ->comment('CC separados por coma');
                $table->integer('horas_validez_token')->default(168);
                $table->string('mail_asunto_aprobacion', 255)
                    ->default('Asiento contable pendiente de aprobación');
                $table->text('mail_texto_aprobacion')->nullable();
                $table->string('mail_asunto_aprobado_solicitante', 255)
                    ->default('Asiento contable aprobado');
                $table->string('mail_asunto_rechazado_solicitante', 255)
                    ->default('Asiento contable rechazado');
                $table->timestamps();
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });

            DB::table('configuracion_asiento_contable')->insert([
                'enviar_mail_aprobacion' => true,
                'mail_aprobador' => null,
                'horas_validez_token' => 168,
                'mail_asunto_aprobacion' => 'Asiento contable pendiente de aprobación',
                'mail_asunto_aprobado_solicitante' => 'Asiento contable aprobado',
                'mail_asunto_rechazado_solicitante' => 'Asiento contable rechazado',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asiento_token');
        Schema::dropIfExists('configuracion_asiento_contable');

        if (Schema::hasTable('asiento')) {
            Schema::table('asiento', function (Blueprint $table) {
                if (Schema::hasColumn('asiento', 'aprobador_id')) {
                    $table->dropForeign('fk_asiento_aprobador');
                }
                $cols = [
                    'estado_aprobacion',
                    'cuentas_no_autorizadas',
                    'aprobador_id',
                    'aprobado_el',
                    'rechazado_el',
                    'motivo_rechazo',
                ];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('asiento', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};

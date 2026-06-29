<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Autorización por correo del encargado de laboratorio para envío R/D en requisiciones de sala.
 */
return new class extends Migration
{
    private const CC_LABORATORIO = '93';

    /** @var list<string> */
    private array $usuariosLaboratorio = [
        'rvaldez',
        'evillagra',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('requisicion_sala_autorizacion_laboratorio')) {
            Schema::create('requisicion_sala_autorizacion_laboratorio', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('requisicion_sala_id');
                $table->string('estado', 1)->default('P')->comment('P pendiente | A autorizada | R rechazada');
                $table->text('motivo_rechazo')->nullable();
                $table->unsignedBigInteger('transferencia_mercaderia_id')->nullable();
                $table->unsignedBigInteger('usuario_proceso_id')->nullable();
                $table->timestamp('fecha_proceso')->nullable();
                $table->timestamps();

                $table->foreign('requisicion_sala_id', 'fk_rs_aut_lab_requisicion')
                    ->references('id')->on('requisicion_sala')
                    ->onDelete('cascade')->onUpdate('restrict');
                $table->foreign('transferencia_mercaderia_id', 'fk_rs_aut_lab_transferencia')
                    ->references('id')->on('transferencia_mercaderia')
                    ->onDelete('set null')->onUpdate('restrict');
                $table->foreign('usuario_proceso_id', 'fk_rs_aut_lab_usuario')
                    ->references('id')->on('usuario')
                    ->onDelete('set null')->onUpdate('restrict');

                $table->index(['requisicion_sala_id', 'estado'], 'idx_rs_aut_lab_req_estado');

                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }

        if (! Schema::hasTable('requisicion_sala_autorizacion_laboratorio_token')) {
            Schema::create('requisicion_sala_autorizacion_laboratorio_token', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('requisicion_sala_autorizacion_laboratorio_id');
                $table->string('token', 80)->unique();
                $table->string('accion', 20)->comment('aprobar | rechazar | visualizar');
                $table->unsignedBigInteger('usuario_destino_id')->nullable();
                $table->timestamp('usado_el')->nullable();
                $table->timestamp('expira_el')->nullable();
                $table->timestamps();

                $table->foreign('requisicion_sala_autorizacion_laboratorio_id', 'fk_rs_aut_lab_token_aut')
                    ->references('id')->on('requisicion_sala_autorizacion_laboratorio')
                    ->onDelete('cascade')->onUpdate('restrict');
                $table->foreign('usuario_destino_id', 'fk_rs_aut_lab_token_usuario')
                    ->references('id')->on('usuario')
                    ->onDelete('set null')->onUpdate('restrict');

                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }

        $this->seedModuloAviso();
    }

    public function down(): void
    {
        Schema::dropIfExists('requisicion_sala_autorizacion_laboratorio_token');
        Schema::dropIfExists('requisicion_sala_autorizacion_laboratorio');

        DB::table('modulo_aviso_destinatario')
            ->whereIn('modulo_aviso_tipo_id', function ($q) {
                $q->select('id')->from('modulo_aviso_tipo')
                    ->where('modulo', 'sala')
                    ->whereIn('codigo', [
                        'requisicion_sala_laboratorio_pendiente',
                        'requisicion_sala_laboratorio_rechazado',
                    ]);
            })
            ->delete();

        DB::table('modulo_aviso_tipo')
            ->where('modulo', 'sala')
            ->whereIn('codigo', [
                'requisicion_sala_laboratorio_pendiente',
                'requisicion_sala_laboratorio_rechazado',
            ])
            ->delete();
    }

    private function seedModuloAviso(): void
    {
        $now = now();
        $tipos = [
            [
                'modulo' => 'sala',
                'codigo' => 'requisicion_sala_laboratorio_pendiente',
                'nombre' => 'Requisición sala R/D — autorización laboratorio',
                'descripcion' => 'Correo al encargado de laboratorio para autorizar o rechazar el envío de mercadería (reparación/devolución).',
                'activo' => true,
                'mail_asunto' => 'Requisición sala Nº {numero}: autorizar envío a laboratorio',
                'mail_texto' => "Requisición de sala Nº {numero} con ítems de reparación o devolución.\n\n"
                    ."Solicitante: {solicitante}\nEmpresa: {empresa}\nCentro de costo: {centro_costo}\n"
                    ."Depósito origen: {deposito_origen}\nDepósito destino (laboratorio): {deposito_laboratorio}\n\n"
                    ."Ítems:\n{detalle_lineas}\n\n"
                    ."Autorizar envío: {link_aprobar}\nRechazar: {link_rechazar}\nConsultar: {link_consulta}",
                'mail_remitente' => null,
                'adjuntar_pdf' => false,
                'incluir_link_consulta' => true,
            ],
            [
                'modulo' => 'sala',
                'codigo' => 'requisicion_sala_laboratorio_rechazado',
                'nombre' => 'Requisición sala R/D — rechazo laboratorio',
                'descripcion' => 'Aviso al solicitante cuando laboratorio rechaza el envío de mercadería.',
                'activo' => true,
                'mail_asunto' => 'Requisición sala Nº {numero}: envío rechazado por laboratorio',
                'mail_texto' => "El encargado de laboratorio rechazó el envío de la requisición de sala Nº {numero}.\n\n"
                    ."Motivo: {motivo_rechazo}\n\nPodés revisar la requisición en: {link_consulta}",
                'mail_remitente' => null,
                'adjuntar_pdf' => false,
                'incluir_link_consulta' => true,
            ],
        ];

        foreach ($tipos as $tipo) {
            $existe = DB::table('modulo_aviso_tipo')
                ->where('modulo', $tipo['modulo'])
                ->where('codigo', $tipo['codigo'])
                ->exists();
            if ($existe) {
                continue;
            }
            DB::table('modulo_aviso_tipo')->insert(array_merge($tipo, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $tipoPendienteId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', 'sala')
            ->where('codigo', 'requisicion_sala_laboratorio_pendiente')
            ->value('id') ?? 0);
        $ccId = (int) (DB::table('centrocosto')->where('codigo', self::CC_LABORATORIO)->value('id') ?? 0);
        $usuarioPorLogname = DB::table('usuario')->pluck('id', 'usuario');

        if ($tipoPendienteId > 0 && $ccId > 0) {
            foreach ($this->usuariosLaboratorio as $logname) {
                $usuarioId = (int) ($usuarioPorLogname[$logname] ?? 0);
                if ($usuarioId <= 0) {
                    continue;
                }
                $existe = DB::table('modulo_aviso_destinatario')
                    ->where('modulo_aviso_tipo_id', $tipoPendienteId)
                    ->where('usuario_id', $usuarioId)
                    ->where('centrocosto_id', $ccId)
                    ->exists();
                if ($existe) {
                    continue;
                }
                DB::table('modulo_aviso_destinatario')->insert([
                    'modulo_aviso_tipo_id' => $tipoPendienteId,
                    'email' => null,
                    'usuario_id' => $usuarioId,
                    'empresa_id' => null,
                    'centrocosto_id' => $ccId,
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};

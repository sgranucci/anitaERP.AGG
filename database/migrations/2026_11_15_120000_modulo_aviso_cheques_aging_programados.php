<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Piloto cheques aging + oleada de avisos batch al despachador ModuloAviso.
 * Parametro días, flag in-app, bitácora de corridas (Fase 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        Schema::table('modulo_aviso_tipo', function (Blueprint $table) {
            if (! Schema::hasColumn('modulo_aviso_tipo', 'parametro_dias')) {
                $table->unsignedSmallInteger('parametro_dias')->nullable()->after('programacion_ultima_en');
            }
            if (! Schema::hasColumn('modulo_aviso_tipo', 'notificar_in_app')) {
                $table->boolean('notificar_in_app')->default(false)->after('parametro_dias');
            }
        });

        if (! Schema::hasTable('modulo_aviso_programado_log')) {
            Schema::create('modulo_aviso_programado_log', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('modulo_aviso_tipo_id')->nullable();
                $table->string('clave', 160);
                $table->string('comando', 255);
                $table->string('origen', 40)->default('cron');
                $table->timestamp('inicio_en')->nullable();
                $table->timestamp('fin_en')->nullable();
                $table->smallInteger('exit_code')->nullable();
                $table->text('salida')->nullable();
                $table->text('error')->nullable();
                $table->timestamps();

                $table->index(['clave', 'inicio_en'], 'idx_aviso_prog_log_clave_inicio');
                $table->foreign('modulo_aviso_tipo_id', 'fk_aviso_prog_log_tipo')
                    ->references('id')->on('modulo_aviso_tipo')->onDelete('set null');
            });
        }

        $this->seedChequesAging();
        $this->activarProgramacionOleada();
    }

    public function down(): void
    {
        if (Schema::hasTable('modulo_aviso_programado_log')) {
            Schema::drop('modulo_aviso_programado_log');
        }

        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        $tipoId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', 'caja')
            ->where('codigo', 'cheques_aging')
            ->value('id') ?? 0);
        if ($tipoId > 0 && Schema::hasTable('modulo_aviso_destinatario')) {
            DB::table('modulo_aviso_destinatario')->where('modulo_aviso_tipo_id', $tipoId)->delete();
        }
        DB::table('modulo_aviso_tipo')
            ->where('modulo', 'caja')
            ->where('codigo', 'cheques_aging')
            ->delete();

        Schema::table('modulo_aviso_tipo', function (Blueprint $table) {
            foreach (['notificar_in_app', 'parametro_dias'] as $col) {
                if (Schema::hasColumn('modulo_aviso_tipo', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    private function seedChequesAging(): void
    {
        $existe = DB::table('modulo_aviso_tipo')
            ->where('modulo', 'caja')
            ->where('codigo', 'cheques_aging')
            ->exists();
        if ($existe) {
            return;
        }

        $now = now();
        $hora = (string) config('cheque.aging_aviso.hora', '09:25');
        if (! preg_match('/^\d{1,2}:\d{2}$/', $hora)) {
            $hora = '09:25';
        }
        $diasProximos = max(0, (int) config('cheque.aging_aviso.dias_proximos', 7));
        $habilitadoConfig = (bool) config('cheque.aging_aviso.habilitado', true);

        $tipoId = (int) DB::table('modulo_aviso_tipo')->insertGetId([
            'modulo' => 'caja',
            'codigo' => 'cheques_aging',
            'nombre' => 'Aviso de cheques por vencer / vencidos',
            'descripcion' => 'Resumen diario de cheques de terceros en cartera vencidos o próximos a vencer (aging). '
                .'Se ejecuta según la programación de este aviso. Ferli no lo envía. '
                .'El parámetro «días próximos» define la ventana de vencimiento próximo.',
            'activo' => true,
            'mail_asunto' => 'AnitaERP: cheques en cartera vencidos / por vencer ({cantidad})',
            'mail_texto' => "Cheques de terceros a revisar (aging cartera, hoy {fecha}):\n\n"
                ."{cheques}\n\n"
                ."Totales: vencidos={vencidos} / próximos={proximos}\n"
                ."Aging cartera: {link_consulta}\n",
            'mail_remitente' => null,
            'adjuntar_pdf' => false,
            'incluir_link_consulta' => true,
            'programacion_habilitada' => $habilitadoConfig,
            'programacion_hora' => $hora,
            'programacion_dias' => '1,2,3,4,5',
            // Evita disparo doble el día del deploy si ya pasó la hora del Kernel.
            'programacion_ultima_en' => $now->toDateString(),
            'parametro_dias' => $diasProximos,
            'notificar_in_app' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $emails = array_filter(array_map(
            'trim',
            explode(',', (string) config('cheque.aging_aviso.emails', ''))
        ));
        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            DB::table('modulo_aviso_destinatario')->insert([
                'modulo_aviso_tipo_id' => $tipoId,
                'email' => strtolower($email),
                'usuario_id' => null,
                'empresa_id' => null,
                'centrocosto_id' => null,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function activarProgramacionOleada(): void
    {
        $now = now();
        $items = [
            [
                'modulo' => 'compras',
                'codigo' => 'ordencompra_alertas_abiertas',
                'hora' => (string) config('compras.oc_alertas_abiertas.hora', '08:15'),
                'habilitada' => (bool) config('compras.oc_alertas_abiertas.habilitado', true),
            ],
            [
                'modulo' => 'compras',
                'codigo' => 'entrega_dia_sin_recepcion',
                'hora' => (string) config('compras.entrega_dia_sin_recepcion.hora', '13:00'),
                'habilitada' => (bool) config('compras.entrega_dia_sin_recepcion.habilitado', true),
            ],
            [
                'modulo' => 'compras',
                'codigo' => 'ordencompra_contrato_vencimiento',
                'hora' => (string) config('compras.contratos_vencimiento.hora', '08:30'),
                'habilitada' => (bool) config('compras.contratos_vencimiento.habilitado', true),
            ],
            [
                'modulo' => 'compras',
                'codigo' => 'suscripcion_comprobante_faltante',
                'hora' => (string) config('compras.suscripcion_comprobantes.hora', '08:45'),
                'habilitada' => (bool) config('compras.suscripcion_comprobantes.habilitado', true),
            ],
            [
                'modulo' => 'compras',
                'codigo' => 'comprobante_proveedor_borrador_pendiente',
                'hora' => (string) config('compras.factura_borrador_aviso.hora', '09:30'),
                'habilitada' => (bool) config('compras.factura_borrador_aviso.habilitado', true),
            ],
            [
                'modulo' => 'arca',
                'codigo' => 'certificado_vencimiento',
                'hora' => (string) config('arca.certificado_aviso.hora', '08:10'),
                'habilitada' => true,
            ],
            [
                'modulo' => 'seguridad',
                'codigo' => 'ingreso_proveedor_recordatorio',
                'hora' => (string) config('ingreso_proveedor.recordatorio_hora', '08:45'),
                'habilitada' => (bool) config('ingreso_proveedor.recordatorio_habilitado', true),
            ],
            [
                'modulo' => 'stock',
                'codigo' => 'prestamo_recordatorio',
                'hora' => '07:30',
                'habilitada' => true,
            ],
        ];

        foreach ($items as $item) {
            $hora = $item['hora'];
            if (! preg_match('/^\d{1,2}:\d{2}$/', $hora)) {
                continue;
            }
            $afectados = DB::table('modulo_aviso_tipo')
                ->where('modulo', $item['modulo'])
                ->where('codigo', $item['codigo'])
                ->where(function ($q) {
                    $q->where('programacion_habilitada', false)
                        ->orWhereNull('programacion_hora');
                })
                ->update([
                    'programacion_habilitada' => $item['habilitada'],
                    'programacion_hora' => $hora,
                    'programacion_dias' => '1,2,3,4,5',
                    'programacion_ultima_en' => $now->toDateString(),
                    'updated_at' => $now,
                ]);
            // Si ya tenía programación (libreta-style), no pisar.
            unset($afectados);
        }
    }
};

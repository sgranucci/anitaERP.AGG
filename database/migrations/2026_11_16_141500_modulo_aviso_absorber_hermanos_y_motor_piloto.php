<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Listados/CI → 08:05 / 08:10
 * - Absorbe definibles, Flash, digests árbol, Crown, AI digest/alerta al Programador
 * - Enciende motor de reglas + activa las 3 piloto
 */
return new class extends Migration
{
    /** @var list<array{modulo: string, codigo: string, nombre: string, descripcion: string, hora: string, dias: string}> */
    private const TIPOS = [
        [
            'modulo' => 'listado',
            'codigo' => 'envios_workbench',
            'nombre' => 'Envíos programados de listados (workbench)',
            'descripcion' => 'Batch listados pagos/IE/tickets. Destinos en listado_envio_programado.',
            'hora' => '08:05',
            'dias' => '1,2,3,4,5,6,7',
            'solo_actualizar_hora' => true,
        ],
        [
            'modulo' => 'consulta_inteligente',
            'codigo' => 'envios_programados',
            'nombre' => 'Envíos programados de Consulta Inteligente',
            'descripcion' => 'Batch CI. Destinos en listado_envio_programado (consulta.inteligente.*).',
            'hora' => '08:10',
            'dias' => '1,2,3,4,5,6,7',
            'solo_actualizar_hora' => true,
        ],
        [
            'modulo' => 'contable',
            'codigo' => 'reportes_definibles',
            'nombre' => 'Distribución reportes definibles (Contable)',
            'descripcion' => 'Poll cada 15′: cada suscripción define día/hora. Reemplaza cron hourly del Kernel.',
            'hora' => '00:00',
            'dias' => '1,2,3,4,5,6,7',
        ],
        [
            'modulo' => 'sueldos',
            'codigo' => 'reportes_definibles',
            'nombre' => 'Distribución reportes definibles (Sueldos)',
            'descripcion' => 'Poll cada 15′ con --ejecutar. Cada suscripción define frecuencia/horario.',
            'hora' => '00:00',
            'dias' => '1,2,3,4,5,6,7',
        ],
        [
            'modulo' => 'flash',
            'codigo' => 'distribuir_reportes',
            'nombre' => 'Flash Report AGG — distribución',
            'descripcion' => 'Poll cada 15′ (solo AGG). Cada suscripción define día/hora.',
            'hora' => '00:00',
            'dias' => '1,2,3,4,5,6,7',
        ],
        [
            'modulo' => 'flash_crown',
            'codigo' => 'distribuir_reportes',
            'nombre' => 'Flash Report Crown — distribución',
            'descripcion' => 'Poll cada 15′ (solo Crown). Gate por config de distribución.',
            'hora' => '00:00',
            'dias' => '1,2,3,4,5,6,7',
        ],
        [
            'modulo' => 'arbolaprobacion',
            'codigo' => 'digest_pendientes',
            'nombre' => 'Digest pendientes de aprobación',
            'descripcion' => 'Resumen diario de Mis aprobaciones pendientes.',
            'hora' => '08:30',
            'dias' => '1,2,3,4,5,6,7',
        ],
        [
            'modulo' => 'arbolaprobacion',
            'codigo' => 'recordatorios',
            'nombre' => 'Recordatorios de árbol de aprobación',
            'descripcion' => 'Recordatorio diario a firmantes con pendientes.',
            'hora' => '09:00',
            'dias' => '1,2,3,4,5,6,7',
        ],
        [
            'modulo' => 'tesoreria_crown',
            'codigo' => 'avisos',
            'nombre' => 'Avisos tesorería Crown',
            'descripcion' => 'Bandeja auditoría Crown + mail (solo Crown). Hora desde parámetro sistema.',
            'hora' => '07:30',
            'dias' => '1,2,3,4,5,6,7',
        ],
        [
            'modulo' => 'ai',
            'codigo' => 'digest_brief',
            'nombre' => 'AI digest brief del día',
            'descripcion' => 'Brief diario del asistente operativo.',
            'hora' => '08:15',
            'dias' => '1,2,3,4,5,6,7',
        ],
        [
            'modulo' => 'ai',
            'codigo' => 'alerta_umbral',
            'nombre' => 'AI alerta por umbral',
            'descripcion' => 'Alertas del asistente por umbrales configurados.',
            'hora' => '08:20',
            'dias' => '1,2,3,4,5,6,7',
        ],
    ];

    private const PILOTO = [
        'piloto_cheque_aging_campanita_admin',
        'piloto_recepcion_confirmada_aviso',
        'piloto_prestamo_solicitud_campanita',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        $now = now();
        $ultima = $now->timezone('America/Argentina/Buenos_Aires')->toDateString();

        foreach (self::TIPOS as $def) {
            $hora = $def['hora'];
            if ($def['modulo'] === 'tesoreria_crown' && $def['codigo'] === 'avisos') {
                try {
                    $hora = \App\Support\Caja\TesoreriaCrownAvisoSupport::hora();
                } catch (\Throwable) {
                    $hora = '07:30';
                }
            }
            if ($def['modulo'] === 'arbolaprobacion' && $def['codigo'] === 'digest_pendientes') {
                $hora = (string) config('arbolaprobacion.digest.hora', '08:30');
            }
            if ($def['modulo'] === 'arbolaprobacion' && $def['codigo'] === 'recordatorios') {
                $hora = (string) config('arbolaprobacion.recordatorio_hora', '09:00');
            }
            if ($def['modulo'] === 'ai' && $def['codigo'] === 'digest_brief') {
                $hora = (string) config('ai.digest.hora', '08:15');
            }
            if ($def['modulo'] === 'ai' && $def['codigo'] === 'alerta_umbral') {
                $hora = (string) config('ai.alerta_umbral.hora', '08:20');
            }

            $existe = DB::table('modulo_aviso_tipo')
                ->where('modulo', $def['modulo'])
                ->where('codigo', $def['codigo'])
                ->exists();

            if ($existe) {
                DB::table('modulo_aviso_tipo')
                    ->where('modulo', $def['modulo'])
                    ->where('codigo', $def['codigo'])
                    ->update([
                        'activo' => true,
                        'programacion_habilitada' => true,
                        'programacion_hora' => $hora,
                        'programacion_dias' => $def['dias'],
                        'programacion_pausada' => false,
                        'programacion_reintentos_max' => 0,
                        'programacion_ultima_en' => $ultima,
                        'updated_at' => $now,
                    ]);

                continue;
            }

            if (! empty($def['solo_actualizar_hora'])) {
                continue;
            }

            DB::table('modulo_aviso_tipo')->insert([
                'modulo' => $def['modulo'],
                'codigo' => $def['codigo'],
                'nombre' => $def['nombre'],
                'descripcion' => $def['descripcion'],
                'activo' => true,
                'mail_asunto' => 'Batch: '.$def['nombre'],
                'mail_texto' => "Despacho vía Programador (comando Artisan). No usa destinatarios del ABM.\n",
                'mail_remitente' => null,
                'adjuntar_pdf' => false,
                'incluir_link_consulta' => false,
                'programacion_habilitada' => true,
                'programacion_hora' => $hora,
                'programacion_dias' => $def['dias'],
                'programacion_cada_n' => 1,
                'programacion_hasta' => null,
                'programacion_ancla' => null,
                'programacion_dia_mes' => null,
                'programacion_skip_feriados' => false,
                'programacion_pausada' => false,
                'programacion_reintentos_max' => 0,
                'programacion_depende_de' => null,
                'programacion_ultima_en' => $ultima,
                'notificar_in_app' => false,
                'notificar_whatsapp' => false,
                'disparador' => 'cron',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (Schema::hasTable('aviso_regla_config')) {
            DB::table('aviso_regla_config')->update([
                'motor_habilitado' => true,
                'updated_at' => $now,
            ]);
        }
        if (Schema::hasTable('aviso_regla')) {
            DB::table('aviso_regla')
                ->whereIn('codigo', self::PILOTO)
                ->update([
                    'activo' => true,
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('aviso_regla')) {
            DB::table('aviso_regla')
                ->whereIn('codigo', self::PILOTO)
                ->update(['activo' => false, 'updated_at' => now()]);
        }
        if (Schema::hasTable('aviso_regla_config')) {
            DB::table('aviso_regla_config')->update([
                'motor_habilitado' => false,
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        // Restaura horas listados/CI previas; no borra tipos (pueden tener logs).
        DB::table('modulo_aviso_tipo')->where('modulo', 'listado')->where('codigo', 'envios_workbench')
            ->update(['programacion_hora' => '07:05', 'updated_at' => now()]);
        DB::table('modulo_aviso_tipo')->where('modulo', 'consulta_inteligente')->where('codigo', 'envios_programados')
            ->update(['programacion_hora' => '07:10', 'updated_at' => now()]);

        $borrar = [
            ['contable', 'reportes_definibles'],
            ['sueldos', 'reportes_definibles'],
            ['flash', 'distribuir_reportes'],
            ['flash_crown', 'distribuir_reportes'],
            ['arbolaprobacion', 'digest_pendientes'],
            ['arbolaprobacion', 'recordatorios'],
            ['tesoreria_crown', 'avisos'],
            ['ai', 'digest_brief'],
            ['ai', 'alerta_umbral'],
        ];
        foreach ($borrar as [$mod, $cod]) {
            DB::table('modulo_aviso_tipo')->where('modulo', $mod)->where('codigo', $cod)->delete();
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Absorbe listados workbench + Consulta Inteligente al Programador
 * (avisos:despachar-programados). Kernel 07:05 / 07:10 queda comentado.
 */
return new class extends Migration
{
    /** @var list<array{modulo: string, codigo: string, nombre: string, descripcion: string, hora: string}> */
    private const TIPOS = [
        [
            'modulo' => 'listado',
            'codigo' => 'envios_workbench',
            'nombre' => 'Envíos programados de listados (workbench)',
            'descripcion' => 'Dispara compras:enviar-listados-programados (pagos, ingresos/egresos, tickets). '
                .'Cada fila activo en listado_envio_programado define email, frecuencia y filtros. '
                .'Pausá o cambiá frecuencia desde el cockpit del Programador.',
            'hora' => '07:05',
        ],
        [
            'modulo' => 'consulta_inteligente',
            'codigo' => 'envios_programados',
            'nombre' => 'Envíos programados de Consulta Inteligente',
            'descripcion' => 'Dispara direccion:enviar-consultas-inteligentes (recursos consulta.inteligente.*). '
                .'Cada fila activo en listado_envio_programado define email, frecuencia y filtros. '
                .'Pausá o cambiá frecuencia desde el cockpit del Programador.',
            'hora' => '07:10',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        $now = now();
        // Evita doble disparo el día del deploy si ya pasó la hora del Kernel viejo.
        $ultima = $now->timezone('America/Argentina/Buenos_Aires')->toDateString();

        foreach (self::TIPOS as $def) {
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
                        'programacion_hora' => $def['hora'],
                        'programacion_dias' => '1,2,3,4,5,6,7',
                        'programacion_pausada' => false,
                        'programacion_reintentos_max' => 0,
                        'updated_at' => $now,
                    ]);

                continue;
            }

            DB::table('modulo_aviso_tipo')->insert([
                'modulo' => $def['modulo'],
                'codigo' => $def['codigo'],
                'nombre' => $def['nombre'],
                'descripcion' => $def['descripcion'],
                'activo' => true,
                'mail_asunto' => 'Batch: '.$def['nombre'],
                'mail_texto' => 'Este tipo no envía mail propio: el comando Artisan reparte Excel '
                    ."según listado_envio_programado.\n",
                'mail_remitente' => null,
                'adjuntar_pdf' => false,
                'incluir_link_consulta' => false,
                'programacion_habilitada' => true,
                'programacion_hora' => $def['hora'],
                'programacion_dias' => '1,2,3,4,5,6,7',
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
    }

    public function down(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }
        foreach (self::TIPOS as $def) {
            DB::table('modulo_aviso_tipo')
                ->where('modulo', $def['modulo'])
                ->where('codigo', $def['codigo'])
                ->delete();
        }
    }
};

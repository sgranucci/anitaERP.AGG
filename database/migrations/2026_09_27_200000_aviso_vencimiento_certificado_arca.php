<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso de vencimiento de certificados ARCA en el módulo de avisos.
 * Destinatarios editables en Configuración → Avisos por módulo.
 */
return new class extends Migration
{
    private const MODULO = 'arca';

    private const CODIGO = 'certificado_vencimiento';

    /** @var list<string> */
    private const EMAILS = [
        'sergiogranucci@gmail.com',
        'gsurace@grupoagg.com',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        $now = now();
        $tipoId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO)
            ->value('id') ?? 0);

        if ($tipoId === 0) {
            $tipoId = (int) DB::table('modulo_aviso_tipo')->insertGetId([
                'modulo' => self::MODULO,
                'codigo' => self::CODIGO,
                'nombre' => 'Certificado ARCA por vencer',
                'descripcion' => 'Cron diario arca:avisar-vencimiento-certificados. '
                    .'Desde 30 días antes del vencimiento envía día por medio '
                    .'(también si ya venció) hasta que se renueve el certificado. '
                    .'Destinatarios en Configuración → Avisos por módulo.',
                'activo' => true,
                'mail_asunto' => 'AnitaERP: certificados ARCA por vencer ({cantidad})',
                'mail_texto' => "Certificados ARCA a renovar (al {fecha}).\n\n"
                    ."En ventana o vencidos: {cantidad}. De ellos vencen hoy o ya vencieron: {cantidad_vencidos}.\n\n"
                    ."{certificados}\n\n"
                    ."El aviso se repite día por medio desde 30 días antes del vencimiento.\n"
                    ."Renovar en Clave Fiscal y subir el .crt en Certificados ARCA: {link_consulta}\n",
                'mail_remitente' => null,
                'adjuntar_pdf' => false,
                'incluir_link_consulta' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($tipoId <= 0 || ! Schema::hasTable('modulo_aviso_destinatario')) {
            return;
        }

        foreach (self::EMAILS as $email) {
            $existe = DB::table('modulo_aviso_destinatario')
                ->where('modulo_aviso_tipo_id', $tipoId)
                ->whereRaw('LOWER(email) = ?', [strtolower($email)])
                ->exists();
            if ($existe) {
                continue;
            }

            DB::table('modulo_aviso_destinatario')->insert([
                'modulo_aviso_tipo_id' => $tipoId,
                'email' => $email,
                'usuario_id' => null,
                'empresa_id' => null,
                'centrocosto_id' => null,
                'activo' => true,
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

        $tipoId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO)
            ->value('id') ?? 0);
        if ($tipoId <= 0) {
            return;
        }

        if (Schema::hasTable('modulo_aviso_destinatario')) {
            DB::table('modulo_aviso_destinatario')
                ->where('modulo_aviso_tipo_id', $tipoId)
                ->delete();
        }

        DB::table('modulo_aviso_tipo')->where('id', $tipoId)->delete();
    }
};

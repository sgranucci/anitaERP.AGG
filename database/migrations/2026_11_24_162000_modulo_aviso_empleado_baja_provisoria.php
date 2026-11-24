<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso baja provisoria de empleado → Lorena Figueroa (lfigueroa).
 * Disparo al programar + robot programado sueldos:avisar-baja-provisoria.
 */
return new class extends Migration
{
    private const MODULO = 'sueldos';

    private const CODIGO = 'empleado_baja_provisoria';

    private const EMAIL_DEFAULT = 'lfigueroa@grupoagg.com';

    private const USUARIO = 'lfigueroa';

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
            $payload = [
                'modulo' => self::MODULO,
                'codigo' => self::CODIGO,
                'nombre' => 'Baja provisoria de empleado',
                'descripcion' => 'Aviso al programar una baja provisoria y recordatorio de pendientes de confirmar como definitiva (efectivización manual).',
                'activo' => true,
                'mail_asunto' => 'Baja provisoria: leg. {legajo} {nombre} — {empresa}',
                'mail_texto' => "Se programó una baja provisoria (el legajo sigue activo hasta confirmar la definitiva).\n\n"
                    ."Empresa: {empresa}\n"
                    ."Legajo: {legajo}\n"
                    ."Nombre: {nombre}\n"
                    ."Fecha egreso: {fecha_egreso}\n"
                    ."Motivo: {motivo}\n"
                    ."Comentario: {comentario}\n\n"
                    ."Para confirmar la baja definitiva, abrí la ficha:\n"
                    .'{link_consulta}',
                'mail_remitente' => null,
                'adjuntar_pdf' => false,
                'incluir_link_consulta' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (Schema::hasColumn('modulo_aviso_tipo', 'programado_activo')) {
                $payload['programado_activo'] = true;
            }
            if (Schema::hasColumn('modulo_aviso_tipo', 'programado_hora')) {
                $payload['programado_hora'] = '08:30';
            }
            if (Schema::hasColumn('modulo_aviso_tipo', 'programado_dias')) {
                $payload['programado_dias'] = '1,2,3,4,5';
            }
            $tipoId = (int) DB::table('modulo_aviso_tipo')->insertGetId($payload);
        }

        if ($tipoId <= 0 || ! Schema::hasTable('modulo_aviso_destinatario')) {
            return;
        }

        $usuarioId = null;
        if (Schema::hasTable('usuario')) {
            $usuarioId = DB::table('usuario')->where('usuario', self::USUARIO)->value('id');
            $usuarioId = $usuarioId ? (int) $usuarioId : null;
        }

        $email = strtolower(self::EMAIL_DEFAULT);
        $ya = DB::table('modulo_aviso_destinatario')
            ->where('modulo_aviso_tipo_id', $tipoId)
            ->where(function ($q) use ($email, $usuarioId) {
                $q->where('email', $email);
                if ($usuarioId) {
                    $q->orWhere('usuario_id', $usuarioId);
                }
            })
            ->exists();

        if (! $ya) {
            DB::table('modulo_aviso_destinatario')->insert([
                'modulo_aviso_tipo_id' => $tipoId,
                'email' => $email,
                'usuario_id' => $usuarioId,
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

        if ($tipoId > 0 && Schema::hasTable('modulo_aviso_destinatario')) {
            DB::table('modulo_aviso_destinatario')
                ->where('modulo_aviso_tipo_id', $tipoId)
                ->delete();
        }

        DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO)
            ->delete();
    }
};

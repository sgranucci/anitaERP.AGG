<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Stock\StkmovIntMailSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reemplazo El Bierzo de l-stkmovint -b: aviso programado lun–vie 21:10 + destinatarios GrupoSocios.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        $now = now();
        $activo = EntornoEmpresaSupport::esElBierzo();
        $codigo = StkmovIntMailSupport::CODIGO_AVISO;

        $tipoId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', StkmovIntMailSupport::MODULO)
            ->where('codigo', $codigo)
            ->value('id') ?? 0);

        $payload = [
            'nombre' => StkmovIntMailSupport::NOMBRE_AVISO,
            'descripcion' => StkmovIntMailSupport::DESCRIPCION_AVISO,
            'activo' => $activo,
            'mail_asunto' => 'Movimientos de stock del {fecha}',
            'mail_texto' => 'Saldos del día G1–G6 (Villafranca + Bierzo). PDF adjunto. Merge ERP+Anita.',
            'mail_remitente' => null,
            'adjuntar_pdf' => true,
            'incluir_link_consulta' => false,
            'programacion_habilitada' => $activo,
            'programacion_hora' => StkmovIntMailSupport::HORA_CRON,
            'programacion_dias' => StkmovIntMailSupport::DIAS_CRON,
            'updated_at' => $now,
        ];

        if ($tipoId === 0) {
            $tipoId = (int) DB::table('modulo_aviso_tipo')->insertGetId(array_merge($payload, [
                'modulo' => StkmovIntMailSupport::MODULO,
                'codigo' => $codigo,
                'created_at' => $now,
            ]));
        } else {
            DB::table('modulo_aviso_tipo')->where('id', $tipoId)->update($payload);
        }

        if ($tipoId <= 0 || ! $activo || ! Schema::hasTable('modulo_aviso_destinatario')) {
            return;
        }

        foreach (StkmovIntMailSupport::EMAILS_DEFAULT as $email) {
            $email = strtolower(trim((string) $email));
            if ($email === '') {
                continue;
            }
            $existe = DB::table('modulo_aviso_destinatario')
                ->where('modulo_aviso_tipo_id', $tipoId)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->exists();
            if ($existe) {
                continue;
            }
            $usuarioId = DB::table('usuario')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->value('id');
            DB::table('modulo_aviso_destinatario')->insert([
                'modulo_aviso_tipo_id' => $tipoId,
                'email' => $email,
                'usuario_id' => $usuarioId ? (int) $usuarioId : null,
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
            ->where('modulo', StkmovIntMailSupport::MODULO)
            ->where('codigo', StkmovIntMailSupport::CODIGO_AVISO)
            ->value('id') ?? 0);

        if ($tipoId <= 0) {
            return;
        }

        if (Schema::hasTable('modulo_aviso_destinatario')) {
            DB::table('modulo_aviso_destinatario')->where('modulo_aviso_tipo_id', $tipoId)->delete();
        }
        DB::table('modulo_aviso_tipo')->where('id', $tipoId)->delete();
    }
};

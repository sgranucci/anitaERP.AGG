<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\ControlNcDescuentoMailSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reemplazo El Bierzo de p-controlnc: aviso programado diario 23:00 + destinatarios legacy.
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
        $codigo = ControlNcDescuentoMailSupport::CODIGO_AVISO;

        $tipoId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', ControlNcDescuentoMailSupport::MODULO)
            ->where('codigo', $codigo)
            ->value('id') ?? 0);

        $payload = [
            'nombre' => ControlNcDescuentoMailSupport::NOMBRE_AVISO,
            'descripcion' => ControlNcDescuentoMailSupport::DESCRIPCION_AVISO,
            'activo' => $activo,
            'mail_asunto' => 'Notas de Credito de Dto. {fecha}',
            'mail_texto' => 'Control de NCP de descuento del día (PDF adjunto). Merge ERP+Anita.',
            'mail_remitente' => null,
            'adjuntar_pdf' => true,
            'incluir_link_consulta' => false,
            'programacion_habilitada' => $activo,
            'programacion_hora' => ControlNcDescuentoMailSupport::HORA_CRON,
            'programacion_dias' => ControlNcDescuentoMailSupport::DIAS_CRON,
            'updated_at' => $now,
        ];

        if ($tipoId === 0) {
            $tipoId = (int) DB::table('modulo_aviso_tipo')->insertGetId(array_merge($payload, [
                'modulo' => ControlNcDescuentoMailSupport::MODULO,
                'codigo' => $codigo,
                'created_at' => $now,
            ]));
        } else {
            DB::table('modulo_aviso_tipo')->where('id', $tipoId)->update($payload);
        }

        if ($tipoId <= 0 || ! $activo || ! Schema::hasTable('modulo_aviso_destinatario')) {
            return;
        }

        foreach (ControlNcDescuentoMailSupport::EMAILS_DEFAULT as $email) {
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
            ->where('modulo', ControlNcDescuentoMailSupport::MODULO)
            ->where('codigo', ControlNcDescuentoMailSupport::CODIGO_AVISO)
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

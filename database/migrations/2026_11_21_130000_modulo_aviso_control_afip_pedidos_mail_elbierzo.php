<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\ControlAfipPedidosMailSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reemplazo El Bierzo de l-pedrem -b: aviso programado sábados 18:00 + 3 destinatarios legacy.
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
        $codigo = ControlAfipPedidosMailSupport::CODIGO_AVISO_RESUMEN;

        $tipoId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', ControlAfipPedidosMailSupport::MODULO)
            ->where('codigo', $codigo)
            ->value('id') ?? 0);

        $payload = [
            'nombre' => ControlAfipPedidosMailSupport::NOMBRE_AVISO,
            'descripcion' => ControlAfipPedidosMailSupport::DESCRIPCION_AVISO,
            'activo' => $activo,
            'mail_asunto' => 'Clientes con problemas de AFIP {fecha}',
            'mail_texto' => 'Listado de clientes con problemas de AFIP (PDF y Excel adjuntos).',
            'mail_remitente' => null,
            'adjuntar_pdf' => true,
            'incluir_link_consulta' => false,
            'programacion_habilitada' => $activo,
            'programacion_hora' => ControlAfipPedidosMailSupport::HORA_CRON,
            'programacion_dias' => ControlAfipPedidosMailSupport::DIAS_CRON,
            'updated_at' => $now,
        ];

        if ($tipoId === 0) {
            $tipoId = (int) DB::table('modulo_aviso_tipo')->insertGetId(array_merge($payload, [
                'modulo' => ControlAfipPedidosMailSupport::MODULO,
                'codigo' => $codigo,
                'created_at' => $now,
            ]));
        } else {
            DB::table('modulo_aviso_tipo')->where('id', $tipoId)->update($payload);
        }

        if ($tipoId <= 0 || ! $activo || ! Schema::hasTable('modulo_aviso_destinatario')) {
            return;
        }

        foreach (ControlAfipPedidosMailSupport::EMAILS_RESUMEN_DEFAULT as $email) {
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
            ->where('modulo', ControlAfipPedidosMailSupport::MODULO)
            ->where('codigo', ControlAfipPedidosMailSupport::CODIGO_AVISO_RESUMEN)
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

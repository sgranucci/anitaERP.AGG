<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\KilosPedidosMailPerfiles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reemplazo El Bierzo de l-pedkilo -b: 4 avisos programados (misma hora/destinos del cron viejo).
 * Gate: solo activa y carga destinatarios en EL BIERZO.
 */
return new class extends Migration
{
    private const MODULO = 'ventas';

    public function up(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        $now = now();
        $activo = EntornoEmpresaSupport::esElBierzo();

        foreach (KilosPedidosMailPerfiles::todos() as $perfilClave => $perfil) {
            $codigo = (string) $perfil['codigo_aviso'];
            $tipoId = (int) (DB::table('modulo_aviso_tipo')
                ->where('modulo', self::MODULO)
                ->where('codigo', $codigo)
                ->value('id') ?? 0);

            $payload = [
                'nombre' => (string) $perfil['nombre'],
                'descripcion' => 'Batch kilos pedidos (merge ERP+Anita). Reemplaza l-pedkilo -b del cron Linux. '
                    .'Perfil '.$perfilClave.'. Destinatarios y horario se editan acá.',
                'activo' => $activo,
                'mail_asunto' => 'Kilos pedidos {fecha}',
                'mail_texto' => 'Listado de kilos pedidos (PDF adjunto). Control ERP vs Anita incluido.',
                'mail_remitente' => null,
                'adjuntar_pdf' => true,
                'incluir_link_consulta' => false,
                'programacion_habilitada' => $activo,
                'programacion_hora' => (string) $perfil['hora'],
                'programacion_dias' => '1,2,3,4,5,6,7',
                'updated_at' => $now,
            ];

            if ($tipoId === 0) {
                $tipoId = (int) DB::table('modulo_aviso_tipo')->insertGetId(array_merge($payload, [
                    'modulo' => self::MODULO,
                    'codigo' => $codigo,
                    'created_at' => $now,
                ]));
            } else {
                DB::table('modulo_aviso_tipo')->where('id', $tipoId)->update($payload);
            }

            if ($tipoId <= 0 || ! $activo || ! Schema::hasTable('modulo_aviso_destinatario')) {
                continue;
            }

            foreach ($perfil['emails_default'] as $email) {
                $email = trim((string) $email);
                if ($email === '') {
                    continue;
                }
                $existe = DB::table('modulo_aviso_destinatario')
                    ->where('modulo_aviso_tipo_id', $tipoId)
                    ->where('email', $email)
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
    }

    public function down(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        foreach (KilosPedidosMailPerfiles::todos() as $perfil) {
            $codigo = (string) $perfil['codigo_aviso'];
            $tipoId = (int) (DB::table('modulo_aviso_tipo')
                ->where('modulo', self::MODULO)
                ->where('codigo', $codigo)
                ->value('id') ?? 0);
            if ($tipoId <= 0) {
                continue;
            }
            if (Schema::hasTable('modulo_aviso_destinatario')) {
                DB::table('modulo_aviso_destinatario')
                    ->where('modulo_aviso_tipo_id', $tipoId)
                    ->delete();
            }
            DB::table('modulo_aviso_tipo')->where('id', $tipoId)->delete();
        }
    }
};

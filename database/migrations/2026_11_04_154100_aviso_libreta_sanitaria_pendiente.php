<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso de empleados que deben la libreta sanitaria.
 * Destinatario inicial: Lorena Figueroa (Capital Humano). Editable en Avisos por módulo.
 */
return new class extends Migration
{
    private const MODULO = 'sueldos';

    private const CODIGO = 'libreta_sanitaria_pendiente';

    private const EMAIL = 'lfigueroa@grupoagg.com';

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
                'nombre' => 'Libreta sanitaria pendiente',
                'descripcion' => 'Cron de días hábiles sueldos:avisar-libreta-sanitaria. '
                    .'Lista los empleados vigentes marcados como "Debe libreta sanitaria". '
                    .'Las bajas no entran. Destinatarios en Configuración → Avisos por módulo.',
                'activo' => true,
                'mail_asunto' => 'AnitaERP: libretas sanitarias pendientes ({cantidad})',
                'mail_texto' => "Empleados que deben la libreta sanitaria al {fecha}.\n\n"
                    ."Cantidad: {cantidad}.\n\n"
                    ."{empleados}\n\n"
                    ."El aviso se repite cada día hábil mientras el legajo siga marcado en la ficha, solapa Laborales.\n"
                    ."Listado de empleados: {link_consulta}\n",
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

        $existe = DB::table('modulo_aviso_destinatario')
            ->where('modulo_aviso_tipo_id', $tipoId)
            ->whereRaw('LOWER(email) = ?', [strtolower(self::EMAIL)])
            ->exists();
        if ($existe) {
            return;
        }

        $usuarioId = Schema::hasTable('usuario')
            ? DB::table('usuario')->whereRaw('LOWER(email) = ?', [strtolower(self::EMAIL)])->value('id')
            : null;

        DB::table('modulo_aviso_destinatario')->insert([
            'modulo_aviso_tipo_id' => $tipoId,
            'email' => self::EMAIL,
            'usuario_id' => $usuarioId ? (int) $usuarioId : null,
            'empresa_id' => null,
            'centrocosto_id' => null,
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
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

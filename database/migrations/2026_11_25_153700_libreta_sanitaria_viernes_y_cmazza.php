<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Libreta sanitaria pendiente: solo viernes + copia a Carla Mazza (cmazza).
 */
return new class extends Migration
{
    private const MODULO = 'sueldos';

    private const CODIGO = 'libreta_sanitaria_pendiente';

    private const EMAIL_COPIA = 'cmazza@grupoagg.com';

    private const USUARIO_COPIA = 'cmazza';

    public function up(): void
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

        $now = now();

        DB::table('modulo_aviso_tipo')
            ->where('id', $tipoId)
            ->update([
                'programacion_dias' => '5',
                'updated_at' => $now,
            ]);

        if (! Schema::hasTable('modulo_aviso_destinatario')) {
            return;
        }

        $usuarioId = null;
        if (Schema::hasTable('usuario')) {
            $usuarioId = DB::table('usuario')->where('usuario', self::USUARIO_COPIA)->value('id');
            $usuarioId = $usuarioId ? (int) $usuarioId : null;
        }

        $email = strtolower(self::EMAIL_COPIA);
        $ya = DB::table('modulo_aviso_destinatario')
            ->where('modulo_aviso_tipo_id', $tipoId)
            ->where(function ($q) use ($email, $usuarioId) {
                $q->whereRaw('LOWER(email) = ?', [$email]);
                if ($usuarioId) {
                    $q->orWhere('usuario_id', $usuarioId);
                }
            })
            ->exists();

        if ($ya) {
            return;
        }

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

        DB::table('modulo_aviso_tipo')
            ->where('id', $tipoId)
            ->update([
                'programacion_dias' => '1,2,3,4,5',
                'updated_at' => now(),
            ]);

        if (! Schema::hasTable('modulo_aviso_destinatario')) {
            return;
        }

        $usuarioId = Schema::hasTable('usuario')
            ? DB::table('usuario')->where('usuario', self::USUARIO_COPIA)->value('id')
            : null;

        DB::table('modulo_aviso_destinatario')
            ->where('modulo_aviso_tipo_id', $tipoId)
            ->where(function ($q) use ($usuarioId) {
                $q->whereRaw('LOWER(email) = ?', [strtolower(self::EMAIL_COPIA)]);
                if ($usuarioId) {
                    $q->orWhere('usuario_id', (int) $usuarioId);
                }
            })
            ->delete();
    }
};

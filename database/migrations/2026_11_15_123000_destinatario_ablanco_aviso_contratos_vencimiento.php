<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contratos OC: destinatario de catálogo ablanco (además del responsable del contrato).
 */
return new class extends Migration
{
    private const MODULO = 'compras';

    /** @var list<string> */
    private const CODIGOS = [
        'ordencompra_contrato_vencimiento',
        'ordencompra_contrato_vencido',
    ];

    private const EMAIL = 'ablanco@grupoagg.com';

    private const USUARIO = 'ablanco';

    public function up(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo') || ! Schema::hasTable('modulo_aviso_destinatario')) {
            return;
        }

        $usuarioId = (int) (DB::table('usuario')
            ->where('usuario', self::USUARIO)
            ->where(function ($q) {
                $q->where('suspendido', false)->orWhereNull('suspendido');
            })
            ->value('id') ?? 0);

        $now = now();
        foreach (self::CODIGOS as $codigo) {
            $tipoId = (int) (DB::table('modulo_aviso_tipo')
                ->where('modulo', self::MODULO)
                ->where('codigo', $codigo)
                ->value('id') ?? 0);
            if ($tipoId <= 0) {
                continue;
            }

            $existe = DB::table('modulo_aviso_destinatario')
                ->where('modulo_aviso_tipo_id', $tipoId)
                ->where(function ($q) use ($usuarioId) {
                    $q->where('email', self::EMAIL);
                    if ($usuarioId > 0) {
                        $q->orWhere('usuario_id', $usuarioId);
                    }
                })
                ->exists();
            if ($existe) {
                continue;
            }

            DB::table('modulo_aviso_destinatario')->insert([
                'modulo_aviso_tipo_id' => $tipoId,
                'email' => self::EMAIL,
                'usuario_id' => $usuarioId > 0 ? $usuarioId : null,
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
        if (! Schema::hasTable('modulo_aviso_tipo') || ! Schema::hasTable('modulo_aviso_destinatario')) {
            return;
        }

        foreach (self::CODIGOS as $codigo) {
            $tipoId = (int) (DB::table('modulo_aviso_tipo')
                ->where('modulo', self::MODULO)
                ->where('codigo', $codigo)
                ->value('id') ?? 0);
            if ($tipoId <= 0) {
                continue;
            }
            DB::table('modulo_aviso_destinatario')
                ->where('modulo_aviso_tipo_id', $tipoId)
                ->where('email', self::EMAIL)
                ->whereNull('usuario_orig_id')
                ->delete();
        }
    }
};

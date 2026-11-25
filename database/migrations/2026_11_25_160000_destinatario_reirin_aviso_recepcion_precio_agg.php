<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AGG: el aviso de recepción con precio distinto a la OC (y el de precio pendiente
 * de aprobación en borrador) ya llegan a Alejandro Blanco; se suma Romina Eirin.
 */
return new class extends Migration
{
    private const MODULO = 'stock';

    private const USUARIO = 'reirin';

    /** @var list<string> */
    private const CODIGOS = [
        'recepcion_proveedor_precio_diferencia',
        'recepcion_proveedor_precio_pendiente_compras',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()
            || ! Schema::hasTable('modulo_aviso_tipo')
            || ! Schema::hasTable('modulo_aviso_destinatario')
            || ! Schema::hasTable('usuario')
        ) {
            return;
        }

        $usuario = DB::table('usuario')
            ->where('usuario', self::USUARIO)
            ->where('suspendido', false)
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->first(['id', 'email']);

        if ($usuario === null) {
            return;
        }

        $email = strtolower(trim((string) $usuario->email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $usuarioId = (int) $usuario->id;
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
                ->where(function ($q) use ($usuarioId, $email) {
                    $q->where('usuario_id', $usuarioId)
                        ->orWhereRaw('LOWER(email) = ?', [$email]);
                })
                ->exists();

            if ($existe) {
                DB::table('modulo_aviso_destinatario')
                    ->where('modulo_aviso_tipo_id', $tipoId)
                    ->where(function ($q) use ($usuarioId, $email) {
                        $q->where('usuario_id', $usuarioId)
                            ->orWhereRaw('LOWER(email) = ?', [$email]);
                    })
                    ->update([
                        'activo' => true,
                        'updated_at' => $now,
                    ]);

                continue;
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
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()
            || ! Schema::hasTable('modulo_aviso_tipo')
            || ! Schema::hasTable('modulo_aviso_destinatario')
            || ! Schema::hasTable('usuario')
        ) {
            return;
        }

        $usuarioId = (int) (DB::table('usuario')
            ->where('usuario', self::USUARIO)
            ->value('id') ?? 0);
        if ($usuarioId <= 0) {
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
                ->where('usuario_id', $usuarioId)
                ->delete();
        }
    }
};

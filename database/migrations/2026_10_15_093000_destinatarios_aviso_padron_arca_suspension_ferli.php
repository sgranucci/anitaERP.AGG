<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * En Ferli el aviso de suspensión por padrón ARCA también va a Romina y Graciela.
 */
return new class extends Migration
{
    private const MODULO = 'compras';

    private const CODIGO = 'proveedor_padron_arca_suspension';

    /** @var list<string> */
    private const USUARIOS = [
        'graciela',
        'Romina',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli() || ! Schema::hasTable('modulo_aviso_destinatario') || ! Schema::hasTable('usuario')) {
            return;
        }

        $tipoId = $this->tipoId();
        if ($tipoId <= 0) {
            return;
        }

        $now = now();
        $usuarios = DB::table('usuario')
            ->whereIn('usuario', self::USUARIOS)
            ->where('suspendido', false)
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->get(['id', 'email']);

        foreach ($usuarios as $usuario) {
            $email = strtolower(trim((string) $usuario->email));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $existe = DB::table('modulo_aviso_destinatario')
                ->where('modulo_aviso_tipo_id', $tipoId)
                ->where(function ($q) use ($usuario, $email) {
                    $q->where('usuario_id', (int) $usuario->id)
                        ->orWhereRaw('LOWER(email) = ?', [$email]);
                })
                ->exists();

            if ($existe) {
                continue;
            }

            DB::table('modulo_aviso_destinatario')->insert([
                'modulo_aviso_tipo_id' => $tipoId,
                'email' => $email,
                'usuario_id' => (int) $usuario->id,
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
        if (! EntornoEmpresaSupport::esFerli() || ! Schema::hasTable('modulo_aviso_destinatario')) {
            return;
        }

        $tipoId = $this->tipoId();
        if ($tipoId <= 0) {
            return;
        }

        $ids = DB::table('usuario')
            ->whereIn('usuario', self::USUARIOS)
            ->pluck('id');

        DB::table('modulo_aviso_destinatario')
            ->where('modulo_aviso_tipo_id', $tipoId)
            ->whereIn('usuario_id', $ids)
            ->delete();
    }

    private function tipoId(): int
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return 0;
        }

        return (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO)
            ->value('id') ?? 0);
    }
};

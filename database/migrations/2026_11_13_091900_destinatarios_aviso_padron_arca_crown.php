<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * En Crown el aviso de suspensión por padrón ARCA va a contaduría / compras locales
 * (cchiappero, eflores, jcofre) y deja de ir a Sergio.
 */
return new class extends Migration
{
    private const MODULO = 'compras';

    private const CODIGO = 'proveedor_padron_arca_suspension';

    /** @var list<string> */
    private const USUARIOS_ALTA = [
        'cchiappero',
        'eflores',
        'jcofre',
    ];

    private const USUARIO_BAJA = 'sergio';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('modulo_aviso_destinatario') || ! Schema::hasTable('usuario')) {
            return;
        }

        $tipoId = $this->tipoId();
        if ($tipoId <= 0) {
            return;
        }

        foreach (self::USUARIOS_ALTA as $login) {
            $this->agregarUsuario($tipoId, $login);
        }
        $this->quitarUsuario($tipoId, self::USUARIO_BAJA);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('modulo_aviso_destinatario')) {
            return;
        }

        $tipoId = $this->tipoId();
        if ($tipoId <= 0) {
            return;
        }

        foreach (self::USUARIOS_ALTA as $login) {
            $this->quitarUsuario($tipoId, $login);
        }
        $this->agregarUsuario($tipoId, self::USUARIO_BAJA);
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

    private function agregarUsuario(int $tipoId, string $login): void
    {
        $usuario = DB::table('usuario')
            ->where('usuario', $login)
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

        $existe = DB::table('modulo_aviso_destinatario')
            ->where('modulo_aviso_tipo_id', $tipoId)
            ->where(function ($q) use ($usuario, $email) {
                $q->where('usuario_id', (int) $usuario->id)
                    ->orWhereRaw('LOWER(email) = ?', [$email]);
            })
            ->exists();

        if ($existe) {
            DB::table('modulo_aviso_destinatario')
                ->where('modulo_aviso_tipo_id', $tipoId)
                ->where(function ($q) use ($usuario, $email) {
                    $q->where('usuario_id', (int) $usuario->id)
                        ->orWhereRaw('LOWER(email) = ?', [$email]);
                })
                ->update([
                    'activo' => true,
                    'updated_at' => now(),
                ]);

            return;
        }

        $now = now();
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

    private function quitarUsuario(int $tipoId, string $login): void
    {
        $ids = DB::table('usuario')
            ->where('usuario', $login)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('modulo_aviso_destinatario')
            ->where('modulo_aviso_tipo_id', $tipoId)
            ->whereIn('usuario_id', $ids)
            ->delete();
    }
};

<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Asigna listar-todos-movimientos-de-stock a todos los roles del sector contaduría
 * (Enc-contaduría, Op-contaduria y futuros roles cuyo nombre contenga "contadur").
 */
return new class extends Migration
{
    private const PERMISO_SLUG = 'listar-todos-movimientos-de-stock';

    public function up(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        foreach ($this->resolverRolesSectorContaduria() as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        foreach ($this->resolverRolesSectorContaduria() as $rolId) {
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->where('rol_id', $rolId)
                ->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /** @return list<int> */
    private function resolverRolesSectorContaduria(): array
    {
        return DB::table('rol')
            ->whereRaw('LOWER(nombre) LIKE ?', ['%contadur%'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
};

<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Portería (op-SEGURIDAD): ver todos los tickets de su empresa + registrar ENTRO/SALIO.
 * Sup/enc ya tenían listar-todos y autorizar.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ROLES = ['op-SEGURIDAD'];

    /** @var list<string> */
    private const SLUGS = [
        'listar-todos-ingreso-proveedor',
        'autorizar-ingreso-proveedor',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permiso') || ! Schema::hasTable('permiso_rol') || ! Schema::hasTable('rol')) {
            return;
        }

        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES)->pluck('id');
        if ($rolIds->isEmpty()) {
            return;
        }

        foreach (self::SLUGS as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId <= 0) {
                continue;
            }
            foreach ($rolIds as $rolId) {
                $existe = DB::table('permiso_rol')
                    ->where('permiso_id', $permisoId)
                    ->where('rol_id', $rolId)
                    ->exists();
                if (! $existe) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permiso') || ! Schema::hasTable('permiso_rol') || ! Schema::hasTable('rol')) {
            return;
        }

        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES)->pluck('id');
        $permisoIds = DB::table('permiso')->whereIn('slug', self::SLUGS)->pluck('id');
        if ($rolIds->isEmpty() || $permisoIds->isEmpty()) {
            return;
        }

        DB::table('permiso_rol')
            ->whereIn('permiso_id', $permisoIds)
            ->whereIn('rol_id', $rolIds)
            ->delete();

        SuitecrmPermiso::flushCachePermisos();
    }
};

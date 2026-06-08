<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Mismos roles que el ABM de mesas (config gastronomía). */
    private const SLUG_REF = 'listar-mesa-gastronomia';

    /** @var list<string> */
    private const SLUGS_MOZO = [
        'listar-mozo-gastronomia',
        'crear-mozo-gastronomia',
        'editar-mozo-gastronomia',
        'actualizar-mozo-gastronomia',
        'borrar-mozo-gastronomia',
    ];

    public function up(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'AGG') {
            return;
        }

        $refPermisoId = (int) (DB::table('permiso')->where('slug', self::SLUG_REF)->value('id') ?? 0);
        if ($refPermisoId === 0) {
            return;
        }

        $rolIds = DB::table('permiso_rol')
            ->where('permiso_id', $refPermisoId)
            ->pluck('rol_id')
            ->unique()
            ->all();

        if ($rolIds === []) {
            return;
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', self::SLUGS_MOZO)->pluck('id')->all();
        if ($permisoIds === []) {
            return;
        }

        foreach ($permisoIds as $permisoId) {
            foreach ($rolIds as $rolId) {
                $rid = (int) $rolId;
                if ($rid <= 0) {
                    continue;
                }
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rid,
                    ]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'AGG') {
            return;
        }

        $refPermisoId = (int) (DB::table('permiso')->where('slug', self::SLUG_REF)->value('id') ?? 0);
        if ($refPermisoId === 0) {
            return;
        }

        $rolIds = DB::table('permiso_rol')
            ->where('permiso_id', $refPermisoId)
            ->pluck('rol_id')
            ->unique()
            ->all();

        $permisoIds = DB::table('permiso')->whereIn('slug', self::SLUGS_MOZO)->pluck('id')->all();
        foreach ($permisoIds as $permisoId) {
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->whereIn('rol_id', $rolIds)
                ->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};

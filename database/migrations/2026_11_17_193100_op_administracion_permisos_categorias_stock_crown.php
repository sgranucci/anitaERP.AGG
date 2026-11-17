<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: completa op-administracion con CRUD de categorías/subcategorías de
 * artículos (los permisos tienen menu_id de tickets, no de stock).
 */
return new class extends Migration
{
    private const ROL = 'op-administracion';

    /** @var list<string> */
    private const SLUGS = [
        'listar-categorias',
        'crear-categorias',
        'editar-categorias',
        'actualizar-categorias',
        'listar-subcategorias',
        'crear-subcategorias',
        'editar-subcategorias',
        'actualizar-subcategorias',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable('rol') || ! Schema::hasTable('permiso_rol')) {
            return;
        }

        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        foreach (self::SLUGS as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId <= 0) {
                continue;
            }
            if (DB::table('permiso_rol')->where('rol_id', $rolId)->where('permiso_id', $permisoId)->exists()) {
                continue;
            }
            DB::table('permiso_rol')->insert([
                'rol_id' => $rolId,
                'permiso_id' => $permisoId,
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', self::SLUGS)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($permisoIds !== []) {
            DB::table('permiso_rol')
                ->where('rol_id', $rolId)
                ->whereIn('permiso_id', $permisoIds)
                ->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};

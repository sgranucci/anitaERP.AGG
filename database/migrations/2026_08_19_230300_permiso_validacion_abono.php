<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permisos de validación de abono. Completar: área / responsable del contrato.
 * Override: administrador. No se asigna completar a operadores de Compras.
 */
return new class extends Migration
{
    private const MENU_URL = 'stock/recepcion-proveedor';

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Completar validación de abono', 'slug' => 'completar-validacion-abono'],
        ['nombre' => 'Override validación de abono', 'slug' => 'override-validacion-abono'],
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            $menuId = (int) (DB::table('menu')->where('url', 'compras/ordencompra')->value('id') ?? 0);
        }

        $adminRolId = (int) (DB::table('rol')
            ->whereRaw('LOWER(nombre) = ?', ['administrador'])
            ->value('id') ?? 0);

        $now = now();
        foreach (self::PERMISOS as $row) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $row['slug'])->value('id') ?? 0);
            if ($permisoId === 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $row['nombre'],
                    'slug' => $row['slug'],
                    'menu_id' => $menuId > 0 ? $menuId : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($adminRolId > 0
                && ! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $adminRolId)->exists()
            ) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $adminRolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $slugs = array_column(self::PERMISOS, 'slug');
        $ids = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($ids->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $ids)->delete();
            DB::table('permiso')->whereIn('id', $ids)->delete();
        }
        SuitecrmPermiso::flushCachePermisos();
    }
};

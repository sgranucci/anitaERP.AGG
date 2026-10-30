<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Anular (borra el asiento) y revertir (compensatorio) una boleta de depósito CHT.
 * Permiso genérico del ERP: corre en todas las instalaciones.
 * Lo reciben los roles que ya depositan (editar/actualizar cheque) y administrador.
 */
return new class extends Migration
{
    private const MENU_URL = 'caja/cheque';

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Anular depósito de cheque', 'slug' => 'anular-deposito-cheque'],
        ['nombre' => 'Revertir depósito de cheque', 'slug' => 'revertir-deposito-cheque'],
    ];

    /** @var list<string> */
    private const PERMISOS_REF = [
        'editar-cheque',
        'actualizar-cheque',
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            return;
        }

        $rolIds = collect();
        foreach (self::PERMISOS_REF as $slugRef) {
            $refId = (int) (DB::table('permiso')->where('slug', $slugRef)->value('id') ?? 0);
            if ($refId > 0) {
                $rolIds = $rolIds->merge(
                    DB::table('permiso_rol')->where('permiso_id', $refId)->pluck('rol_id')
                );
            }
        }
        $adminId = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
        if ($adminId > 0) {
            $rolIds = $rolIds->push($adminId);
        }
        $rolIds = $rolIds->map(static fn ($id) => (int) $id)->filter(static fn ($id) => $id > 0)->unique();

        foreach (self::PERMISOS as $perm) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $perm['slug'])->value('id') ?? 0);
            if ($permisoId === 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $perm['nombre'],
                    'slug' => $perm['slug'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'nombre' => $perm['nombre'],
                    'menu_id' => $menuId,
                    'updated_at' => now(),
                ]);
            }

            foreach ($rolIds as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
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
        $slugs = array_column(self::PERMISOS, 'slug');
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};

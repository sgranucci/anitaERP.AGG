<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permiso para emitir ND por cheque rechazado desde Caja.
 * Se cuelga del menú caja/cheque y hereda roles de listar/editar-cheque si existen;
 * si no, asigna a administrador.
 */
return new class extends Migration
{
    private const MENU_URL = 'caja/cheque';

    private const PERMISO = 'generar-nota-de-debito-cheque';

    private const PERMISO_NOMBRE = 'Generar nota de débito por cheque rechazado';

    private const PERMISOS_REF = [
        'editar-cheque',
        'listar-cheque',
        'crear-cheque',
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => self::PERMISO_NOMBRE,
                'slug' => self::PERMISO,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'nombre' => self::PERMISO_NOMBRE,
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);
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
        $rolIds = $rolIds->merge(
            DB::table('menu_rol')->where('menu_id', $menuId)->pluck('rol_id')
        );
        $adminId = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
        if ($adminId > 0) {
            $rolIds = $rolIds->push($adminId);
        }

        foreach ($rolIds->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique() as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = DB::table('permiso')->where('slug', self::PERMISO)->value('id');
        if ($permisoId) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};

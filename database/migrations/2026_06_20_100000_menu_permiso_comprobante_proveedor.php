<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU_URL = 'compras/comprobante-proveedor';

    private const MENU_PADRE_COMPRAS = 110;

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar comprobantes de proveedor', 'slug' => 'listar-comprobante-proveedor'],
        ['nombre' => 'Crear comprobante de proveedor', 'slug' => 'crear-comprobante-proveedor'],
        ['nombre' => 'Editar comprobante de proveedor', 'slug' => 'editar-comprobante-proveedor'],
        ['nombre' => 'Actualizar comprobante de proveedor', 'slug' => 'actualizar-comprobante-proveedor'],
        ['nombre' => 'Borrar comprobante de proveedor', 'slug' => 'borrar-comprobante-proveedor'],
        ['nombre' => 'Contabilizar comprobante de proveedor', 'slug' => 'contabilizar-comprobante-proveedor'],
    ];

    public function up(): void
    {
        if (Schema::hasTable('comprobante_proveedor') && ! Schema::hasColumn('comprobante_proveedor', 'origen_entrada')) {
            Schema::table('comprobante_proveedor', function ($table) {
                $table->string('origen_entrada', 20)->nullable()->after('modo_carga');
            });
        }

        $parentMenuId = (int) (DB::table('menu')->where('id', self::MENU_PADRE_COMPRAS)->value('id') ?? 0);
        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')->where('url', 'compras/proveedor')->value('menu_id') ?? self::MENU_PADRE_COMPRAS);
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0) + 1;
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);

        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => 'Comprobantes proveedor',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-file-text-o',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $parentMenuId,
                'nombre' => 'Comprobantes proveedor',
                'orden' => $orden,
                'icono' => 'fa-file-text-o',
                'updated_at' => now(),
            ]);
        }

        $permisoIds = [];
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
                    'menu_id' => $menuId,
                    'nombre' => $perm['nombre'],
                    'updated_at' => now(),
                ]);
            }
            $permisoIds[] = $permisoId;
        }

        $rolIds = $this->resolverRolIdsDesdePrecarga();
        foreach ($rolIds as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            foreach ($permisoIds as $permisoId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
            if (! DB::table('menu_rol')->where('menu_id', $parentMenuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $parentMenuId, 'rol_id' => $rolId]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (Schema::hasColumn('comprobante_proveedor', 'origen_entrada')) {
            Schema::table('comprobante_proveedor', function ($table) {
                $table->dropColumn('origen_entrada');
            });
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('permiso')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /** @return list<int> */
    private function resolverRolIdsDesdePrecarga(): array
    {
        $refPermisoId = (int) (DB::table('permiso')->where('slug', 'listar-precarga-proveedores')->value('id') ?? 0);
        if ($refPermisoId > 0) {
            return DB::table('permiso_rol')->where('permiso_id', $refPermisoId)->pluck('rol_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        }

        $encPagos = (int) (DB::table('rol')->where('nombre', 'Enc-pagos')->value('id') ?? 0);

        return $encPagos > 0 ? [$encPagos] : [];
    }
};

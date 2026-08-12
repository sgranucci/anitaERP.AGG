<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permisos anular / revertir OP proveedores (+ cablear borrar/confirmar existentes al menú).
 */
return new class extends Migration
{
    private const MENU_URL = 'compras/pagoproveedor';

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Anular pago a proveedores', 'slug' => 'anular-pagoproveedor'],
        ['nombre' => 'Revertir pago a proveedores', 'slug' => 'revertir-pagoproveedor'],
        ['nombre' => 'Borrar pago a proveedores', 'slug' => 'borrar-pagoproveedor'],
        ['nombre' => 'Confirmar pago a proveedores', 'slug' => 'confirmar-pagoproveedor'],
        ['nombre' => 'Marcar pagada OP proveedores', 'slug' => 'marcar-pagada-pagoproveedor'],
        ['nombre' => 'Marcar conciliada OP proveedores', 'slug' => 'marcar-conciliada-pagoproveedor'],
    ];

    /** Roles base (match exacto o LIKE). */
    private const ROLES = [
        ['nombre' => 'administrador'],
        ['nombre' => 'Enc-contaduría', 'like' => 'Enc-contadur%'],
        ['nombre' => 'Op-contaduria', 'like' => 'Op-contadur%'],
        ['nombre' => 'Enc-finanzas', 'like' => 'Enc-finanz%'],
        ['nombre' => 'Op-Finanzas', 'like' => 'Op-Finanz%'],
        ['nombre' => 'Enc-tesorería', 'like' => 'Enc-tesorer%'],
        ['nombre' => 'enc-Tesoreria Operativa', 'like' => 'enc-Tesoreria Operativa%'],
        ['nombre' => 'Ger-Tesoreria', 'like' => 'Ger-Tesorer%'],
        ['nombre' => 'Op-tesoreria', 'like' => 'Op-tesorer%'],
        ['nombre' => 'op-Tesoreria Operativa', 'like' => 'op-Tesoreria Operativa%'],
        ['nombre' => 'Sup-tesoreria', 'like' => 'Sup-tesorer%'],
        ['nombre' => 'Enc-pagos', 'like' => 'Enc-pago%'],
        ['nombre' => 'Op-Pagos', 'like' => 'Op-Pago%'],
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $rolIds = $this->resolverRolIds();

        foreach (self::PERMISOS as $perm) {
            $permisoId = $this->upsertPermiso($perm['nombre'], $perm['slug'], $menuId);
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
        $slugs = ['anular-pagoproveedor', 'revertir-pagoproveedor', 'marcar-pagada-pagoproveedor', 'marcar-conciliada-pagoproveedor'];
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /** @return list<int> */
    private function resolverRolIds(): array
    {
        $ids = [];
        foreach (self::ROLES as $rol) {
            $q = DB::table('rol');
            if (! empty($rol['like'])) {
                $q->where('nombre', 'like', $rol['like']);
            } else {
                $q->where('nombre', $rol['nombre']);
            }
            foreach ($q->pluck('id') as $id) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($id === 0) {
            return (int) DB::table('permiso')->insertGetId([
                'nombre' => $nombre,
                'slug' => $slug,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('permiso')->where('id', $id)->update([
            'nombre' => $nombre,
            'menu_id' => $menuId,
            'updated_at' => now(),
        ]);

        return $id;
    }
};

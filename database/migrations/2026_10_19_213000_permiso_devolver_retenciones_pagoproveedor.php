<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permiso para devolución parcial de retenciones (DEV RET) sobre OP confirmadas.
 */
return new class extends Migration
{
    private const MENU_URL = 'compras/pagoproveedor';

    private const SLUG = 'devolver-retenciones-pagoproveedor';

    private const NOMBRE = 'Devolver retenciones OP proveedores';

    /** @var list<array{nombre: string, like?: string}> */
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

        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => self::NOMBRE,
                'slug' => self::SLUG,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'nombre' => self::NOMBRE,
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);
        }

        foreach ($this->resolverRolIds() as $rolId) {
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
        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
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
};

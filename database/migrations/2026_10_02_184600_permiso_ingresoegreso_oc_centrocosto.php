<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ingresos/egresos: ver órdenes de pago de compras cuya orden de compra
 * pertenece al centro de costo del usuario, aunque las haya cargado tesorería.
 *
 * Lo usa capital humano (opcont-capitalhumano): el centro de costo de esa ficha
 * es Capital Humano, así que el listado incluye esas OC.
 */
return new class extends Migration
{
    private const MENU_IE = 'caja/ingresoegreso';

    private const SLUG = 'usuario-ingresos-egresos-oc-centrocosto';

    private const NOMBRE = 'Ver ingresos/egresos de OC de su centro de costo';

    private const ROL = 'opcont-capitalhumano';

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_IE)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $permisoId = $this->upsertPermiso($menuId);
        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        $this->asignar($permisoId, $rolId);

        PermisoCacheSupport::forgetRol($rolId);
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

    private function upsertPermiso(int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        $payload = [
            'nombre' => self::NOMBRE,
            'menu_id' => $menuId,
            'updated_at' => now(),
        ];

        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, [
            'slug' => self::SLUG,
            'created_at' => now(),
        ]));
    }

    private function asignar(int $permisoId, int $rolId): void
    {
        if ($permisoId <= 0 || $rolId <= 0) {
            return;
        }
        if (DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            return;
        }
        DB::table('permiso_rol')->insert([
            'permiso_id' => $permisoId,
            'rol_id' => $rolId,
        ]);
    }
};

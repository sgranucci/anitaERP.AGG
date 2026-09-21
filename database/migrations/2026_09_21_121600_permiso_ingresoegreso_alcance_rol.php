<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ingresos/egresos: ver los movimientos de los usuarios del mismo rol.
 *
 * En Administración el centro de costo lo comparten muchos usuarios además de pagos,
 * así que el alcance por CC muestra de más. Enc-pagos / Op-Pagos pasan a este permiso
 * y dejan el de centro de costo.
 */
return new class extends Migration
{
    private const MENU_IE = 'caja/ingresoegreso';

    private const SLUG = 'usuario-ingresos-egresos-rol';

    private const NOMBRE = 'Ver ingresos/egresos de los usuarios de su rol';

    private const SLUG_CENTROCOSTO = 'usuario-ingresos-egresos-centrocosto';

    /** @var list<array{nombre: string, like: string}> */
    private const ROLES_PAGOS = [
        ['nombre' => 'Enc-pagos', 'like' => 'Enc-pago%'],
        ['nombre' => 'Op-Pagos', 'like' => 'Op-Pago%'],
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_IE)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $permisoId = $this->upsertPermiso($menuId);
        $rolIds = $this->rolesPagos();

        foreach ($rolIds as $rolId) {
            $this->asignar($permisoId, $rolId);
        }

        $ccId = (int) (DB::table('permiso')->where('slug', self::SLUG_CENTROCOSTO)->value('id') ?? 0);
        if ($ccId > 0 && $rolIds !== []) {
            DB::table('permiso_rol')
                ->where('permiso_id', $ccId)
                ->whereIn('rol_id', $rolIds)
                ->delete();
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

        $ccId = (int) (DB::table('permiso')->where('slug', self::SLUG_CENTROCOSTO)->value('id') ?? 0);
        if ($ccId > 0) {
            foreach ($this->rolesEncPagos() as $rolId) {
                $this->asignar($ccId, $rolId);
            }
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

    /** @return list<int> */
    private function rolesPagos(): array
    {
        $ids = [];
        foreach (self::ROLES_PAGOS as $rol) {
            $encontrados = DB::table('rol')
                ->where(function ($q) use ($rol) {
                    $q->where('nombre', $rol['nombre'])
                        ->orWhere('nombre', 'like', $rol['like']);
                })
                ->pluck('id');
            foreach ($encontrados as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /** @return list<int> */
    private function rolesEncPagos(): array
    {
        return DB::table('rol')
            ->where(function ($q) {
                $q->where('nombre', 'Enc-pagos')
                    ->orWhere('nombre', 'like', 'Enc-pago%');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
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

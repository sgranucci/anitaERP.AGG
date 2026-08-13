<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enc-pagos / Op-Pagos podían anular y revertir IE/OPP pero no listarlos ni entrar al menú.
 * Sin listar-todos, además el alcance por centro de costo oculta OPPs cargados por otros
 * (ej. la primera OPP del 11/08 hecha por un usuario de otro CC).
 */
return new class extends Migration
{
    private const MENU_IE = 'caja/ingresoegreso';

    /** @var list<string> */
    private const PERMISOS = [
        'listar-ingresos-egresos-caja',
        'listar-todos-ingresos-egresos-caja',
        'crear-ingresos-egresos-caja',
        'editar-ingresos-egresos-caja',
        'actualizar-ingresos-egresos-caja',
    ];

    public function up(): void
    {
        $rolIds = $this->rolesPagos();
        if ($rolIds === []) {
            return;
        }

        foreach (self::PERMISOS as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId === 0) {
                continue;
            }

            foreach ($rolIds as $rolId) {
                $existe = DB::table('permiso_rol')
                    ->where('permiso_id', $permisoId)
                    ->where('rol_id', $rolId)
                    ->exists();

                if (! $existe) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_IE)->value('id') ?? 0);
        if ($menuId > 0) {
            foreach ($rolIds as $rolId) {
                $existe = DB::table('menu_rol')
                    ->where('menu_id', $menuId)
                    ->where('rol_id', $rolId)
                    ->exists();

                if (! $existe) {
                    DB::table('menu_rol')->insert([
                        'menu_id' => $menuId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        $this->invalidarCachePermisos();
    }

    public function down(): void
    {
        $rolIds = $this->rolesPagos();
        if ($rolIds === []) {
            return;
        }

        $permisoIds = DB::table('permiso')
            ->whereIn('slug', self::PERMISOS)
            ->pluck('id')
            ->all();

        if ($permisoIds !== []) {
            DB::table('permiso_rol')
                ->whereIn('permiso_id', $permisoIds)
                ->whereIn('rol_id', $rolIds)
                ->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_IE)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')
                ->where('menu_id', $menuId)
                ->whereIn('rol_id', $rolIds)
                ->delete();
        }

        $this->invalidarCachePermisos();
    }

    /**
     * @return list<int>
     */
    private function rolesPagos(): array
    {
        return DB::table('rol')
            ->where('nombre', 'like', '%pagos%')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function invalidarCachePermisos(): void
    {
        try {
            cache()->tags('Permiso')->flush();
        } catch (\Throwable) {
            cache()->flush();
        }
    }
};

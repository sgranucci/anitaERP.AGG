<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ingresos/egresos:
 * - Anular físico y borrar: solo administrador (el resto usa Revertir).
 * - Alcance tipo requisiciones: permiso de centro de costo + listar-todos existente.
 * - El listado ya excluye cobranzas POS (cobranza_id) en IngresoEgresoVisibilidadSupport.
 */
return new class extends Migration
{
    private const MENU_IE = 'caja/ingresoegreso';

    private const SLUG_ANULAR = 'anular-ingresos-egresos-caja';

    private const SLUG_BORRAR = 'borrar-ingresos-egresos-caja';

    private const SLUG_ANULAR_PAGO_SP = 'anular-pago-solicitud-pago';

    private const SLUG_CENTROCOSTO = 'usuario-ingresos-egresos-centrocosto';

    private const ROL_ADMIN = 'administrador';

    public function up(): void
    {
        $adminRolId = (int) (DB::table('rol')->where('nombre', self::ROL_ADMIN)->value('id') ?? 0);

        $this->restringirSoloAdmin(self::SLUG_ANULAR, $adminRolId);
        $this->restringirSoloAdmin(self::SLUG_BORRAR, $adminRolId);
        $this->restringirSoloAdmin(self::SLUG_ANULAR_PAGO_SP, $adminRolId);
        $this->crearPermisoCentrocosto($adminRolId);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG_CENTROCOSTO)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function restringirSoloAdmin(string $slug, int $adminRolId): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        DB::table('permiso_rol')
            ->where('permiso_id', $permisoId)
            ->when($adminRolId > 0, fn ($q) => $q->where('rol_id', '!=', $adminRolId))
            ->delete();

        if ($adminRolId > 0
            && ! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $adminRolId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $adminRolId,
            ]);
        }
    }

    private function crearPermisoCentrocosto(int $adminRolId): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_IE)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG_CENTROCOSTO)->value('id') ?? 0);
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Ver ingresos/egresos de su centro de costo',
                'slug' => self::SLUG_CENTROCOSTO,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'nombre' => 'Ver ingresos/egresos de su centro de costo',
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);
        }

        // Roles que listan IE pero no ven todos: alcance por CC (antes era implícito).
        $rolIds = DB::table('rol')
            ->where(function ($q) {
                $q->whereRaw('LOWER(nombre) LIKE ?', ['%finanz%'])
                    ->orWhere('nombre', 'like', 'Enc-finanzas%')
                    ->orWhere('nombre', 'like', 'Op-Finanzas%');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        // Quien ya tiene listar-ingresos y no tiene listar-todos también recibe CC.
        $listarId = (int) (DB::table('permiso')->where('slug', 'listar-ingresos-egresos-caja')->value('id') ?? 0);
        $todosId = (int) (DB::table('permiso')->where('slug', 'listar-todos-ingresos-egresos-caja')->value('id') ?? 0);
        if ($listarId > 0) {
            $conListar = DB::table('permiso_rol')->where('permiso_id', $listarId)->pluck('rol_id')->map(fn ($id) => (int) $id)->all();
            $conTodos = $todosId > 0
                ? DB::table('permiso_rol')->where('permiso_id', $todosId)->pluck('rol_id')->map(fn ($id) => (int) $id)->all()
                : [];
            foreach (array_diff($conListar, $conTodos) as $rolId) {
                $rolIds[] = (int) $rolId;
            }
        }

        if ($adminRolId > 0) {
            $rolIds[] = $adminRolId;
        }

        $rolIds = array_values(array_unique(array_filter($rolIds)));

        foreach ($rolIds as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }
};

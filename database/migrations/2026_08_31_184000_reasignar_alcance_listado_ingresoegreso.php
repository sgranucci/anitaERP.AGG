<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reasigna alcance de listado IE:
 * - listar-todos-ingresos-egresos-caja → contaduría (+ administrador)
 * - usuario-ingresos-egresos-centrocosto → Enc-finanzas, Op-Finanzas, Enc-pagos
 * - listar-ingresos-egresos-caja → resto de roles con acceso a IE (base)
 */
return new class extends Migration
{
    private const SLUG_LISTAR = 'listar-ingresos-egresos-caja';

    private const SLUG_TODOS = 'listar-todos-ingresos-egresos-caja';

    private const SLUG_CENTROCOSTO = 'usuario-ingresos-egresos-centrocosto';

    private const MENU_IE = 'caja/ingresoegreso';

    /** @var list<string> */
    private const ROLES_TODOS = [
        'administrador',
        'Enc-contaduría',
        'Op-contaduria',
        'Sup-contaduria',
    ];

    /** @var list<string> */
    private const ROLES_CENTROCOSTO = [
        'Enc-finanzas',
        'Op-Finanzas',
        'Enc-pagos',
    ];

    public function up(): void
    {
        $listarId = $this->permisoId(self::SLUG_LISTAR);
        $todosId = $this->permisoId(self::SLUG_TODOS);
        $ccId = $this->permisoId(self::SLUG_CENTROCOSTO);

        if ($todosId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $todosId)->delete();
            foreach ($this->rolIdsPorNombres(self::ROLES_TODOS) as $rolId) {
                $this->asignar($todosId, $rolId);
            }
            // Cualquier otro rol contadur% (por si hay variantes)
            foreach ($this->rolIdsLike('%contadur%') as $rolId) {
                $this->asignar($todosId, $rolId);
            }
            $adminId = $this->rolIdExacto('administrador');
            if ($adminId > 0) {
                $this->asignar($todosId, $adminId);
            }
        }

        if ($ccId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $ccId)->delete();
            foreach ($this->rolIdsPorNombres(self::ROLES_CENTROCOSTO) as $rolId) {
                $this->asignar($ccId, $rolId);
            }
        }

        if ($listarId > 0) {
            foreach ($this->rolesConAccesoIe() as $rolId) {
                // Contaduría y admin ya ven todos; el resto (y ellos también) necesitan listar base.
                $this->asignar($listarId, $rolId);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        SuitecrmPermiso::flushCachePermisos();
    }

    private function permisoId(string $slug): int
    {
        return (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
    }

    private function rolIdExacto(string $nombre): int
    {
        return (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
    }

    /** @param list<string> $nombres @return list<int> */
    private function rolIdsPorNombres(array $nombres): array
    {
        return DB::table('rol')
            ->whereIn('nombre', $nombres)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<int> */
    private function rolIdsLike(string $like): array
    {
        return DB::table('rol')
            ->where('nombre', 'like', $like)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Roles con acceso al ABM IE: menú + roles del pedido (contaduría / finanzas / pagos).
     *
     * @return list<int>
     */
    private function rolesConAccesoIe(): array
    {
        $ids = [];

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_IE)->value('id') ?? 0);
        if ($menuId > 0) {
            foreach (DB::table('menu_rol')->where('menu_id', $menuId)->pluck('rol_id') as $rolId) {
                $ids[] = (int) $rolId;
            }
        }

        foreach (array_merge(self::ROLES_TODOS, self::ROLES_CENTROCOSTO, ['Op-Pagos']) as $nombre) {
            $id = $this->rolIdExacto($nombre);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
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

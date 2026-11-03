<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crown: Enc-contaduría (Celina) recibe Módulo de Caja con todos los permisos,
 * excepto Bingo / Flash / Rendiciones (y subárboles). Incluye Cobranzas.
 */
return new class extends Migration
{
    private const ROL = 'Enc-contaduría';

    private const MODULO_CAJA = 'Módulo de Caja';

    /**
     * Raíces bajo Módulo de Caja a excluir (nombre exacto del menú).
     *
     * @var list<string>
     */
    private const RAICES_EXCLUIDAS = [
        'Bingo',
        'Flash',
        'Rendiciones',
        'Rendición de máquinas',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $rolId = $this->rolId();
        $cajaId = $this->moduloCajaId();
        if ($rolId <= 0 || $cajaId <= 0) {
            return;
        }

        [$menuIds, $permisoIds] = $this->menusYPermisosObjetivo($cajaId);
        $this->asignarMenus($rolId, $menuIds);
        $this->asignarPermisos($rolId, $permisoIds);
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $rolId = $this->rolId();
        $cajaId = $this->moduloCajaId();
        if ($rolId <= 0 || $cajaId <= 0) {
            return;
        }

        [$menuIds, $permisoIds] = $this->menusYPermisosObjetivo($cajaId);

        if ($menuIds !== []) {
            DB::table('menu_rol')
                ->where('rol_id', $rolId)
                ->whereIn('menu_id', $menuIds)
                ->delete();
        }
        if ($permisoIds !== []) {
            DB::table('permiso_rol')
                ->where('rol_id', $rolId)
                ->whereIn('permiso_id', $permisoIds)
                ->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function rolId(): int
    {
        return (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
    }

    private function moduloCajaId(): int
    {
        return (int) (DB::table('menu')
            ->where('nombre', self::MODULO_CAJA)
            ->where('menu_id', 0)
            ->value('id') ?? 0);
    }

    /**
     * @return array{0: list<int>, 1: list<int>}
     */
    private function menusYPermisosObjetivo(int $cajaId): array
    {
        $porPadre = [];
        foreach (DB::table('menu')->get(['id', 'menu_id', 'nombre', 'url']) as $menu) {
            $porPadre[(int) $menu->menu_id][] = $menu;
        }

        $excluidos = [];
        foreach ($porPadre[$cajaId] ?? [] as $hijo) {
            $nombre = (string) $hijo->nombre;
            if (! in_array($nombre, self::RAICES_EXCLUIDAS, true)) {
                continue;
            }
            foreach ($this->descendientes($porPadre, (int) $hijo->id) as $id) {
                $excluidos[$id] = true;
            }
        }

        // Talonario de rendición queda fuera aunque no esté bajo "Rendiciones".
        foreach (DB::table('menu')->where('url', 'caja/talonariorendicion')->pluck('id') as $id) {
            $excluidos[(int) $id] = true;
        }

        $incluidos = [];
        foreach ($this->descendientes($porPadre, $cajaId) as $id) {
            if (isset($excluidos[$id])) {
                continue;
            }
            $incluidos[] = $id;
        }

        $permisoIds = DB::table('permiso')
            ->whereIn('menu_id', $incluidos)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return [$incluidos, $permisoIds];
    }

    /**
     * @param  array<int, list<object>>  $porPadre
     * @return list<int>
     */
    private function descendientes(array $porPadre, int $id): array
    {
        $ids = [$id];
        foreach ($porPadre[$id] ?? [] as $hijo) {
            $ids = array_merge($ids, $this->descendientes($porPadre, (int) $hijo->id));
        }

        return $ids;
    }

    /**
     * @param  list<int>  $menuIds
     */
    private function asignarMenus(int $rolId, array $menuIds): void
    {
        foreach (array_chunk($menuIds, 200) as $lote) {
            $ya = DB::table('menu_rol')
                ->where('rol_id', $rolId)
                ->whereIn('menu_id', $lote)
                ->pluck('menu_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $nuevos = array_values(array_diff($lote, $ya));
            if ($nuevos === []) {
                continue;
            }
            DB::table('menu_rol')->insert(array_map(
                fn (int $menuId) => ['rol_id' => $rolId, 'menu_id' => $menuId],
                $nuevos
            ));
        }
    }

    /**
     * @param  list<int>  $permisoIds
     */
    private function asignarPermisos(int $rolId, array $permisoIds): void
    {
        foreach (array_chunk($permisoIds, 200) as $lote) {
            $ya = DB::table('permiso_rol')
                ->where('rol_id', $rolId)
                ->whereIn('permiso_id', $lote)
                ->pluck('permiso_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $nuevos = array_values(array_diff($lote, $ya));
            if ($nuevos === []) {
                continue;
            }
            DB::table('permiso_rol')->insert(array_map(
                fn (int $permisoId) => ['rol_id' => $rolId, 'permiso_id' => $permisoId],
                $nuevos
            ));
        }
    }
};

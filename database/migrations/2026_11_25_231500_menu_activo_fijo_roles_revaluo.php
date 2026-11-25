<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El submenú «Activo Fijo» (rubros / índices / revalúo / asiento) quedó sin
 * menu_rol para Contaduría y roles afines: solo Control de Gestión lo veía.
 * Sin el padre, getMenu() no muestra los hijos aunque tengan permiso.
 */
return new class extends Migration
{
    private const MENU_MODULO = 'Módulo Contable';

    private const MENU_SUBMENU = 'Activo Fijo';

    /** @var list<string> */
    private const HIJOS_URL = [
        'contable/bien-uso-rubro',
        'contable/bien-uso-subrubro',
        'contable/bien-uso-indice',
        'contable/bien-uso-revaluo',
        'contable/bien-uso-asiento',
    ];

    public function up(): void
    {
        $moduloId = (int) (DB::table('menu')
            ->where('nombre', self::MENU_MODULO)
            ->where('url', '#')
            ->where('menu_id', 0)
            ->orderBy('id')
            ->value('id') ?? 0);
        if ($moduloId === 0) {
            return;
        }

        $submenuId = (int) (DB::table('menu')
            ->where('nombre', self::MENU_SUBMENU)
            ->where('menu_id', $moduloId)
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($submenuId === 0) {
            // Si los hijos existen bajo otro padre con ese nombre, usar ese id.
            $submenuId = (int) (DB::table('menu')
                ->where('nombre', self::MENU_SUBMENU)
                ->where('url', '#')
                ->orderBy('id')
                ->value('id') ?? 0);
        }

        if ($submenuId === 0) {
            $orden = (int) (DB::table('menu')->where('menu_id', $moduloId)->max('orden') ?? 0) + 1;
            $submenuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $moduloId,
                'nombre' => self::MENU_SUBMENU,
                'url' => '#',
                'orden' => $orden,
                'icono' => 'fa-building',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $submenuId)->update([
                'menu_id' => $moduloId,
                'url' => '#',
                'updated_at' => now(),
            ]);
        }

        $hijoIds = DB::table('menu')
            ->whereIn('url', self::HIJOS_URL)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        foreach ($hijoIds as $hijoId) {
            DB::table('menu')->where('id', $hijoId)->update([
                'menu_id' => $submenuId,
                'updated_at' => now(),
            ]);
        }

        $rolIds = $this->rolesDesdeHijosYPermisos($hijoIds);
        foreach ($rolIds as $rolId) {
            $this->asegurarMenuRol($submenuId, $rolId);
            $this->asegurarMenuRol($moduloId, $rolId);
            foreach ($hijoIds as $hijoId) {
                $this->asegurarMenuRol($hijoId, $rolId);
            }
            PermisoCacheSupport::forgetRol($rolId);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        // No revierte roles: quitar Activo Fijo a Contaduría dejaría el módulo invisible de nuevo.
    }

    /**
     * @param  list<int>  $hijoIds
     * @return list<int>
     */
    private function rolesDesdeHijosYPermisos(array $hijoIds): array
    {
        $rolIds = [];

        if ($hijoIds !== []) {
            $rolIds = array_merge(
                $rolIds,
                DB::table('menu_rol')->whereIn('menu_id', $hijoIds)->pluck('rol_id')->all()
            );
        }

        $permisoIds = DB::table('permiso')
            ->whereIn('slug', [
                'listar-bien-uso',
                'listar-bien-uso-revaluo',
                'listar-bien-uso-rubro',
                'listar-bien-uso-subrubro',
                'listar-bien-uso-indice',
                'listar-bien-uso-asiento',
            ])
            ->pluck('id')
            ->all();

        if ($permisoIds !== []) {
            $rolIds = array_merge(
                $rolIds,
                DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->pluck('rol_id')->all()
            );
        }

        $rolIds = array_merge(
            $rolIds,
            DB::table('rol')
                ->where(function ($q) {
                    $q->whereRaw('LOWER(nombre) LIKE ?', ['%contadur%'])
                        ->orWhereIn('nombre', [
                            'administrador',
                            'Enc-impuestos',
                            'Enc-admin',
                            'Ger-administracion',
                            'Enc-sistemas',
                        ]);
                })
                ->pluck('id')
                ->all()
        );

        return collect($rolIds)
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function asegurarMenuRol(int $menuId, int $rolId): void
    {
        if ($menuId <= 0 || $rolId <= 0) {
            return;
        }
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert([
                'menu_id' => $menuId,
                'rol_id' => $rolId,
            ]);
        }
    }
};

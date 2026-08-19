<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Flash contable: además de Reportes Contables, queda visible en el sector Impuestos
 * (Presentaciones ARCA) y se asigna la cadena de padres a Enc/Op-impuestos.
 */
return new class extends Migration
{
    private const MENU_URL = 'contable/flash-contable';

    private const MENU_NOMBRE = 'Flash contable';

    private const PERMISO_SLUG = 'listar-flash-contable';

    public function up(): void
    {
        $menuContableId = $this->resolverMenuContableId();
        if ($menuContableId <= 0) {
            return;
        }

        $padreImpuestosId = $this->resolverPadreSectorImpuestos();
        $menuImpuestosId = $padreImpuestosId > 0
            ? $this->upsertMenuEnPadre($padreImpuestosId)
            : 0;

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        $rolIds = $this->resolverRolIdsImpuestos();

        foreach ($rolIds as $rolId) {
            $this->vincularCadenaMenu($menuContableId, $rolId);
            if ($menuImpuestosId > 0) {
                $this->vincularCadenaMenu($menuImpuestosId, $rolId);
            }
            $this->vincularPermisoRol($permisoId, $rolId);
        }

        SuitecrmPermiso::flushCachePermisos();
        $this->forgetPermisoRolCache($rolIds);
    }

    public function down(): void
    {
        $padreImpuestosId = $this->resolverPadreSectorImpuestos();
        if ($padreImpuestosId <= 0) {
            return;
        }

        $menuImpuestosId = (int) (DB::table('menu')
            ->where('url', self::MENU_URL)
            ->where('menu_id', $padreImpuestosId)
            ->value('id') ?? 0);
        $menuContableId = $this->resolverMenuContableId();
        if ($menuImpuestosId > 0 && $menuImpuestosId === $menuContableId) {
            return;
        }

        if ($menuImpuestosId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuImpuestosId)->delete();
            DB::table('menu')->where('id', $menuImpuestosId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverMenuContableId(): int
    {
        $reportesId = $this->resolverMenuPorNombre('Reportes Contables');
        if ($reportesId > 0) {
            $id = (int) (DB::table('menu')
                ->where('url', self::MENU_URL)
                ->where('menu_id', $reportesId)
                ->value('id') ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return (int) (DB::table('menu')->where('url', self::MENU_URL)->orderBy('id')->value('id') ?? 0);
    }

    private function resolverPadreSectorImpuestos(): int
    {
        $moduloContableId = $this->resolverMenuPorNombre('Módulo Contable');
        if ($moduloContableId > 0) {
            $arcaId = (int) (DB::table('menu')
                ->where('menu_id', $moduloContableId)
                ->where('nombre', 'Presentaciones ARCA')
                ->where('url', '#')
                ->value('id') ?? 0);
            if ($arcaId > 0) {
                return $arcaId;
            }
        }

        $impuestosPadrones = $this->resolverMenuPorNombre('Impuestos y Padrones');
        if ($impuestosPadrones > 0) {
            return $impuestosPadrones;
        }

        return (int) (DB::table('menu')
            ->where('nombre', 'Impuestos')
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    private function upsertMenuEnPadre(int $padreId): int
    {
        $existente = (int) (DB::table('menu')
            ->where('url', self::MENU_URL)
            ->where('menu_id', $padreId)
            ->value('id') ?? 0);
        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $payload = [
            'menu_id' => $padreId,
            'nombre' => self::MENU_NOMBRE,
            'url' => self::MENU_URL,
            'icono' => 'fa-bolt',
            'updated_at' => now(),
        ];

        if ($existente > 0) {
            DB::table('menu')->where('id', $existente)->update($payload);

            return $existente;
        }

        return (int) DB::table('menu')->insertGetId(array_merge($payload, [
            'orden' => $orden,
            'created_at' => now(),
        ]));
    }

    private function vincularCadenaMenu(int $menuId, int $rolId): void
    {
        $actual = $menuId;
        $vistos = [];
        while ($actual > 0 && ! isset($vistos[$actual])) {
            $vistos[$actual] = true;
            $this->vincularMenuRol($actual, $rolId);
            $actual = (int) (DB::table('menu')->where('id', $actual)->value('menu_id') ?? 0);
        }
    }

    /**
     * @return list<int>
     */
    private function resolverRolIdsImpuestos(): array
    {
        $ids = [];
        foreach (['administrador'] as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        foreach (['Enc-impuest%', 'Op-impuest%', 'Sup-impuest%', 'Ger-impuest%'] as $like) {
            foreach (DB::table('rol')->where('nombre', 'like', $like)->pluck('id') as $id) {
                $ids[] = (int) $id;
            }
        }

        $menuImpuestosId = (int) (DB::table('menu')
            ->where('nombre', 'Impuestos')
            ->where('url', 'configuracion/impuesto')
            ->value('id') ?? 0);
        if ($menuImpuestosId > 0) {
            foreach (DB::table('menu_rol')->where('menu_id', $menuImpuestosId)->pluck('rol_id') as $id) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
    }

    private function resolverMenuPorNombre(string $nombre): int
    {
        return (int) (DB::table('menu')
            ->where('nombre', $nombre)
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    private function vincularMenuRol(int $menuId, int $rolId): void
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

    private function vincularPermisoRol(int $permisoId, int $rolId): void
    {
        if ($permisoId <= 0 || $rolId <= 0) {
            return;
        }
        if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }
    }

    /** @param list<int> $rolIds */
    private function forgetPermisoRolCache(array $rolIds): void
    {
        foreach ($rolIds as $rolId) {
            try {
                cache()->tags('Permiso')->forget('Permiso.rolid.'.$rolId);
            } catch (\Throwable) {
            }
        }
    }
};

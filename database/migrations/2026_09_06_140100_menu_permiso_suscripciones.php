<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú + permisos del módulo Suscripciones (Compras).
 * Cuelga junto a Órdenes de compra / Contratos.
 */
return new class extends Migration
{
    private const URL = 'compras/suscripciones';

    private const NOMBRE = 'Suscripciones';

    private const ICONO = 'fa-refresh';

    private const URL_HERMANO = 'compras/ordencompra';

    /** @var array<string, string> slug => nombre */
    private const PERMISOS = [
        'listar-suscripcion' => 'Listar suscripciones (OC abiertas)',
        'crear-suscripcion' => 'Crear / editar suscripciones',
        'aprobar-suscripcion' => 'Aprobar suscripciones pendientes',
    ];

    public function up(): void
    {
        $hermano = DB::table('menu')->where('url', self::URL_HERMANO)->first();
        if (! $hermano) {
            $comprasId = $this->resolverMenuComprasId();
            if ($comprasId === 0) {
                return;
            }
            $padreId = $comprasId;
            $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
            $rolIds = $this->rolesComprasFallback();
        } else {
            $padreId = (int) $hermano->menu_id;
            $orden = (int) $hermano->orden + 1;
            $rolIds = DB::table('menu_rol')
                ->where('menu_id', $hermano->id)
                ->pluck('rol_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        DB::table('menu')
            ->where('menu_id', $padreId)
            ->where('orden', '>=', $orden)
            ->where('url', '!=', self::URL)
            ->update(['orden' => DB::raw('orden + 1'), 'updated_at' => now()]);

        $menuId = $this->upsertMenu($padreId, $orden);

        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }

        foreach (self::PERMISOS as $slug => $nombre) {
            $permisoId = $this->upsertPermiso($nombre, $slug, $menuId);
            foreach ($rolIds as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoIds = DB::table('permiso')->whereIn('slug', array_keys(self::PERMISOS))->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverMenuComprasId(): int
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('nombre', 'like', '%Compras%')
            ->orderBy('id')
            ->value('id') ?? 0);

        return $id;
    }

    /** @return list<int> */
    private function rolesComprasFallback(): array
    {
        return DB::table('rol')
            ->where(function ($q) {
                $q->where('nombre', 'like', '%compras%')
                    ->orWhere('nombre', 'like', '%Compras%')
                    ->orWhere('nombre', 'like', '%administrador%')
                    ->orWhere('nombre', 'like', '%Admin%');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function upsertMenu(int $padreId, int $orden): int
    {
        $id = (int) (DB::table('menu')->where('url', self::URL)->value('id') ?? 0);
        $payload = [
            'nombre' => self::NOMBRE,
            'url' => self::URL,
            'menu_id' => $padreId,
            'orden' => $orden,
            'icono' => self::ICONO,
            'updated_at' => now(),
        ];

        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId(array_merge($payload, ['created_at' => now()]));
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = ['nombre' => $nombre, 'slug' => $slug, 'menu_id' => $menuId, 'updated_at' => now()];

        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, ['created_at' => now()]));
    }
};

<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * 1) motivoegreso_id opcional en la corrida (filtro tipico de liquidacion final).
 * 2) Menus + permisos ABM acumuladores y parametros (submenu Tablas de Sueldos).
 */
return new class extends Migration
{
    private const MENU_MODULO = 'Módulo Sueldos y Jornales';

    private const MENU_SUBMENU = 'Tablas de Sueldos';

    /** @var list<array{url: string, nombre: string, permisos: list<array{nombre: string, slug: string}>}> */
    private const HIJOS = [
        [
            'url' => 'sueldos/acumulador',
            'nombre' => 'Acumuladores',
            'permisos' => [
                ['nombre' => 'Listar acumulador sueldos', 'slug' => 'listar-acumulador-sueldos'],
                ['nombre' => 'Crear acumulador sueldos', 'slug' => 'crear-acumulador-sueldos'],
                ['nombre' => 'Editar acumulador sueldos', 'slug' => 'editar-acumulador-sueldos'],
                ['nombre' => 'Actualizar acumulador sueldos', 'slug' => 'actualizar-acumulador-sueldos'],
                ['nombre' => 'Borrar acumulador sueldos', 'slug' => 'borrar-acumulador-sueldos'],
            ],
        ],
        [
            'url' => 'sueldos/parametro',
            'nombre' => 'Parámetros de liquidación',
            'permisos' => [
                ['nombre' => 'Listar parametro sueldos', 'slug' => 'listar-parametro-sueldos'],
                ['nombre' => 'Crear parametro sueldos', 'slug' => 'crear-parametro-sueldos'],
                ['nombre' => 'Editar parametro sueldos', 'slug' => 'editar-parametro-sueldos'],
                ['nombre' => 'Actualizar parametro sueldos', 'slug' => 'actualizar-parametro-sueldos'],
                ['nombre' => 'Borrar parametro sueldos', 'slug' => 'borrar-parametro-sueldos'],
            ],
        ],
    ];

    public function up(): void
    {
        Schema::table('liquidacion_sueldos', function (Blueprint $table) {
            if (! Schema::hasColumn('liquidacion_sueldos', 'motivoegreso_id')) {
                $table->unsignedBigInteger('motivoegreso_id')->nullable()->after('tipo');
                $table->index('motivoegreso_id', 'liquidacion_motivoegreso_ix');
            }
        });

        $this->instalarMenus();
    }

    public function down(): void
    {
        foreach (self::HIJOS as $hijo) {
            $slugs = array_map(fn ($p) => $p['slug'], $hijo['permisos']);
            $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
            if ($permisoIds->isNotEmpty()) {
                DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
                DB::table('permiso')->whereIn('id', $permisoIds)->delete();
            }
            $menuId = (int) (DB::table('menu')->where('url', $hijo['url'])->value('id') ?? 0);
            if ($menuId > 0) {
                DB::table('menu_rol')->where('menu_id', $menuId)->delete();
                DB::table('menu')->where('id', $menuId)->delete();
            }
        }

        Schema::table('liquidacion_sueldos', function (Blueprint $table) {
            if (Schema::hasColumn('liquidacion_sueldos', 'motivoegreso_id')) {
                $table->dropIndex('liquidacion_motivoegreso_ix');
                $table->dropColumn('motivoegreso_id');
            }
        });

        SuitecrmPermiso::flushCachePermisos();
    }

    private function instalarMenus(): void
    {
        $moduloId = (int) (DB::table('menu')->where('nombre', self::MENU_MODULO)->where('menu_id', 0)->value('id') ?? 0);
        if ($moduloId === 0) {
            $ordenModulo = (int) (DB::table('menu')->where('menu_id', 0)->max('orden') ?? 0) + 1;
            $moduloId = (int) DB::table('menu')->insertGetId([
                'nombre' => self::MENU_MODULO, 'url' => '#', 'menu_id' => 0,
                'orden' => $ordenModulo, 'icono' => 'fa-money', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $submenuId = (int) (DB::table('menu')->where('nombre', self::MENU_SUBMENU)->where('menu_id', $moduloId)->value('id') ?? 0);
        if ($submenuId === 0) {
            $ordenSubmenu = (int) (DB::table('menu')->where('menu_id', $moduloId)->max('orden') ?? 0) + 1;
            $submenuId = (int) DB::table('menu')->insertGetId([
                'nombre' => self::MENU_SUBMENU, 'url' => '#', 'menu_id' => $moduloId,
                'orden' => $ordenSubmenu, 'icono' => 'fa-table', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $rolIds = DB::table('rol')
            ->where('nombre', 'administrador')
            ->orWhere('nombre', 'like', '%apital%umano%')
            ->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        foreach ($rolIds as $rolId) {
            $this->asegurarMenuRol($moduloId, $rolId);
            $this->asegurarMenuRol($submenuId, $rolId);
        }

        foreach (self::HIJOS as $hijo) {
            $orden = (int) (DB::table('menu')->where('menu_id', $submenuId)->max('orden') ?? 0) + 1;
            $existente = (int) (DB::table('menu')->where('url', $hijo['url'])->value('id') ?? 0);
            if ($existente > 0) {
                $orden = (int) (DB::table('menu')->where('id', $existente)->value('orden') ?? $orden);
            }
            $menuId = $this->upsertMenuHijo($hijo['url'], $hijo['nombre'], $submenuId, $orden);
            $permisoIds = [];
            foreach ($hijo['permisos'] as $perm) {
                $permisoIds[] = $this->upsertPermiso($perm['nombre'], $perm['slug'], $menuId);
            }
            foreach ($rolIds as $rolId) {
                $this->asegurarMenuRol($menuId, $rolId);
                foreach ($permisoIds as $permisoId) {
                    if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                        DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                    }
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertMenuHijo(string $url, string $nombre, int $padreId, int $orden): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        $payload = ['nombre' => $nombre, 'url' => $url, 'menu_id' => $padreId, 'orden' => $orden, 'icono' => null, 'updated_at' => now()];
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

    private function asegurarMenuRol(int $menuId, int $rolId): void
    {
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
        }
    }
};

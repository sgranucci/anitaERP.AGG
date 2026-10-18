<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú y permisos del módulo contable de bienes de uso (estándar ERP).
 * Sin EntornoEmpresaSupport — aplica AGG, Crown y demás.
 */
return new class extends Migration
{
    private const MENU_PADRE = 'Módulo Contable';

    /** @var list<array{url: string, nombre: string, icono: string, permisos: list<array{nombre: string, slug: string}>}> */
    private const MENUS = [
        [
            'url' => 'contable/bien-uso-rubro',
            'nombre' => 'Rubros de bienes de uso',
            'icono' => 'fa-folder',
            'permisos' => [
                ['nombre' => 'Listar rubros bienes de uso', 'slug' => 'listar-bien-uso-rubro'],
                ['nombre' => 'Crear rubros bienes de uso', 'slug' => 'crear-bien-uso-rubro'],
                ['nombre' => 'Editar rubros bienes de uso', 'slug' => 'editar-bien-uso-rubro'],
                ['nombre' => 'Actualizar rubros bienes de uso', 'slug' => 'actualizar-bien-uso-rubro'],
                ['nombre' => 'Borrar rubros bienes de uso', 'slug' => 'borrar-bien-uso-rubro'],
            ],
        ],
        [
            'url' => 'contable/bien-uso-subrubro',
            'nombre' => 'Subrubros de bienes de uso',
            'icono' => 'fa-folder-open',
            'permisos' => [
                ['nombre' => 'Listar subrubros bienes de uso', 'slug' => 'listar-bien-uso-subrubro'],
                ['nombre' => 'Crear subrubros bienes de uso', 'slug' => 'crear-bien-uso-subrubro'],
                ['nombre' => 'Editar subrubros bienes de uso', 'slug' => 'editar-bien-uso-subrubro'],
                ['nombre' => 'Actualizar subrubros bienes de uso', 'slug' => 'actualizar-bien-uso-subrubro'],
                ['nombre' => 'Borrar subrubros bienes de uso', 'slug' => 'borrar-bien-uso-subrubro'],
            ],
        ],
        [
            'url' => 'contable/bien-uso-indice',
            'nombre' => 'Índices de actualización',
            'icono' => 'fa-chart-line',
            'permisos' => [
                ['nombre' => 'Listar índices bienes de uso', 'slug' => 'listar-bien-uso-indice'],
                ['nombre' => 'Crear índices bienes de uso', 'slug' => 'crear-bien-uso-indice'],
                ['nombre' => 'Editar índices bienes de uso', 'slug' => 'editar-bien-uso-indice'],
                ['nombre' => 'Actualizar índices bienes de uso', 'slug' => 'actualizar-bien-uso-indice'],
                ['nombre' => 'Borrar índices bienes de uso', 'slug' => 'borrar-bien-uso-indice'],
            ],
        ],
        [
            'url' => 'contable/bien-uso-revaluo',
            'nombre' => 'Revalúo contable bienes de uso',
            'icono' => 'fa-balance-scale',
            'permisos' => [
                ['nombre' => 'Listar revalúo bienes de uso', 'slug' => 'listar-bien-uso-revaluo'],
            ],
        ],
        [
            'url' => 'contable/bien-uso-asiento',
            'nombre' => 'Asiento amortizaciones bienes de uso',
            'icono' => 'fa-file-invoice-dollar',
            'permisos' => [
                ['nombre' => 'Listar asiento bienes de uso', 'slug' => 'listar-bien-uso-asiento'],
                ['nombre' => 'Ejecutar asiento bienes de uso', 'slug' => 'ejecutar-bien-uso-asiento'],
            ],
        ],
    ];

    public function up(): void
    {
        $padreId = (int) (DB::table('menu')
            ->where('nombre', self::MENU_PADRE)
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
        if ($padreId === 0) {
            return;
        }

        $roles = $this->rolesConAcceso();
        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0);

        foreach (self::MENUS as $cfg) {
            $orden++;
            $menuId = $this->upsertMenu($cfg['url'], $cfg['nombre'], $padreId, $orden, $cfg['icono']);
            foreach (array_unique([$menuId, $padreId]) as $mid) {
                foreach ($roles as $rolId) {
                    if (! DB::table('menu_rol')->where('menu_id', $mid)->where('rol_id', $rolId)->exists()) {
                        DB::table('menu_rol')->insert(['menu_id' => $mid, 'rol_id' => $rolId]);
                    }
                }
            }
            foreach ($cfg['permisos'] as $permiso) {
                $permisoId = $this->upsertPermiso($permiso['nombre'], $permiso['slug'], $menuId);
                foreach ($roles as $rolId) {
                    if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                        DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                    }
                }
            }
        }

        // Propagar menú a quien ya tiene listar-bien-uso
        $permisoListarBien = (int) (DB::table('permiso')->where('slug', 'listar-bien-uso')->value('id') ?? 0);
        if ($permisoListarBien > 0) {
            $rolesBien = DB::table('permiso_rol')->where('permiso_id', $permisoListarBien)->pluck('rol_id')
                ->map(fn ($id) => (int) $id)->all();
            foreach (self::MENUS as $cfg) {
                $menuId = (int) (DB::table('menu')->where('url', $cfg['url'])->value('id') ?? 0);
                if ($menuId === 0) {
                    continue;
                }
                foreach ($rolesBien as $rolId) {
                    if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                        DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
                    }
                    foreach ($cfg['permisos'] as $permiso) {
                        $pid = (int) (DB::table('permiso')->where('slug', $permiso['slug'])->value('id') ?? 0);
                        if ($pid > 0 && ! DB::table('permiso_rol')->where('permiso_id', $pid)->where('rol_id', $rolId)->exists()) {
                            DB::table('permiso_rol')->insert(['permiso_id' => $pid, 'rol_id' => $rolId]);
                        }
                    }
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        foreach (self::MENUS as $cfg) {
            foreach ($cfg['permisos'] as $permiso) {
                $pid = (int) (DB::table('permiso')->where('slug', $permiso['slug'])->value('id') ?? 0);
                if ($pid > 0) {
                    DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
                    DB::table('permiso')->where('id', $pid)->delete();
                }
            }
            $menuId = (int) (DB::table('menu')->where('url', $cfg['url'])->value('id') ?? 0);
            if ($menuId > 0) {
                DB::table('menu_rol')->where('menu_id', $menuId)->delete();
                DB::table('menu')->where('id', $menuId)->delete();
            }
        }
        SuitecrmPermiso::flushCachePermisos();
    }

    /** @return list<int> */
    private function rolesConAcceso(): array
    {
        return DB::table('rol')
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
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = [
            'nombre' => $nombre,
            'menu_id' => $menuId > 0 ? $menuId : null,
            'updated_at' => now(),
        ];
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, [
            'slug' => $slug,
            'created_at' => now(),
        ]));
    }

    private function upsertMenu(string $url, string $nombre, int $padre, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        if ($id === 0) {
            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $padre,
                'nombre' => $nombre,
                'url' => $url,
                'orden' => $orden,
                'icono' => $icono,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::table('menu')->where('id', $id)->update([
            'menu_id' => $padre,
            'nombre' => $nombre,
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ]);

        return $id;
    }
};

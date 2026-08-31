<?php

use App\Services\Contable\ContabilidadCuentaAutomaticaSeedService;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU_MODULO = 'Módulo Sueldos y Jornales';

    private const MENU_TABLAS = 'Tablas de Sueldos';

    private const URL = 'sueldos/imputacion-concepto';

    private const PERMISOS = [
        ['nombre' => 'Listar imputacion concepto sueldos', 'slug' => 'listar-imputacion-concepto-sueldos'],
        ['nombre' => 'Crear imputacion concepto sueldos', 'slug' => 'crear-imputacion-concepto-sueldos'],
        ['nombre' => 'Editar imputacion concepto sueldos', 'slug' => 'editar-imputacion-concepto-sueldos'],
        ['nombre' => 'Actualizar imputacion concepto sueldos', 'slug' => 'actualizar-imputacion-concepto-sueldos'],
        ['nombre' => 'Borrar imputacion concepto sueldos', 'slug' => 'borrar-imputacion-concepto-sueldos'],
    ];

    public function up(): void
    {
        $moduloId = $this->asegurarMenu(self::MENU_MODULO, 0, 'fa-money');
        $tablasId = $this->asegurarMenu(self::MENU_TABLAS, $moduloId, 'fa-table');

        $existente = (int) (DB::table('menu')->where('url', self::URL)->value('id') ?? 0);
        $orden = $existente > 0
            ? (int) (DB::table('menu')->where('id', $existente)->value('orden') ?? 0)
            : (int) (DB::table('menu')->where('menu_id', $tablasId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenuHijo(self::URL, 'Imputación contable de conceptos', $tablasId, $orden);

        $permisoIds = [];
        foreach (self::PERMISOS as $perm) {
            $permisoIds[] = $this->upsertPermiso($perm['nombre'], $perm['slug'], $menuId);
        }

        $rolIds = $this->resolverRolIds();
        foreach ($rolIds as $rolId) {
            $this->asegurarMenuRol($moduloId, $rolId);
            $this->asegurarMenuRol($tablasId, $rolId);
            $this->asegurarMenuRol($menuId, $rolId);
            foreach ($permisoIds as $permisoId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
        }

        if (Schema::hasTable('contabilidad_cuenta_automatica')) {
            app(ContabilidadCuentaAutomaticaSeedService::class)
                ->asegurarCatalogoEmpresasConUsuariosAsignados();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $slugs = array_column(self::PERMISOS, 'slug');
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        $menuIds = DB::table('menu')->where('url', self::URL)->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menu')->whereIn('id', $menuIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function asegurarMenu(string $nombre, int $padreId, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('nombre', $nombre)->where('menu_id', $padreId)->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;

        return (int) DB::table('menu')->insertGetId([
            'nombre' => $nombre,
            'url' => '#',
            'menu_id' => $padreId,
            'orden' => $orden,
            'icono' => $icono,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function upsertMenuHijo(string $url, string $nombre, int $padreId, int $orden): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        $payload = [
            'nombre' => $nombre,
            'url' => $url,
            'menu_id' => $padreId,
            'orden' => $orden,
            'icono' => null,
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

    private function asegurarMenuRol(int $menuId, int $rolId): void
    {
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
        }
    }

    /**
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        return DB::table('rol')
            ->where('nombre', 'administrador')
            ->orWhere('nombre', 'like', '%apital%umano%')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
};

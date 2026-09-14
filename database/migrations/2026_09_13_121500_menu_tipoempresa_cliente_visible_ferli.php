<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Calzados Ferli: dejar visible «Tipos de empresa» en Tablas de ventas
 * con URL canónica ventas/tipoempresa-cliente + permisos + seed desde tipempresa.
 */
return new class extends Migration
{
    private const MENU_LEGACY_URL = 'ventas/tipoempresa';

    private const MENU_URL = 'ventas/tipoempresa-cliente';

    private const MENU_NOMBRE = 'Tipos de empresa';

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Crear tipo empresa cliente', 'slug' => 'crear-tipo-empresa-cliente'],
        ['nombre' => 'Listar tipos empresa cliente', 'slug' => 'listar-tipo-empresa-cliente'],
        ['nombre' => 'Editar tipo empresa cliente', 'slug' => 'editar-tipo-empresa-cliente'],
        ['nombre' => 'Actualizar tipo empresa cliente', 'slug' => 'actualizar-tipo-empresa-cliente'],
        ['nombre' => 'Borrar tipo empresa cliente', 'slug' => 'borrar-tipo-empresa-cliente'],
    ];

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-admin',
        'Enc-contaduría',
        'Admin-ventas',
        'Ventas',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $parentId = (int) (DB::table('menu')
            ->where('nombre', 'Tablas de ventas')
            ->where('url', '#')
            ->value('id') ?? 53);

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            $menuId = (int) (DB::table('menu')->where('url', self::MENU_LEGACY_URL)->value('id') ?? 0);
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $parentId)->max('orden') ?? 0) + 1;
        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentId,
                'nombre' => self::MENU_NOMBRE,
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $parentId,
                'nombre' => self::MENU_NOMBRE,
                'url' => self::MENU_URL,
                'updated_at' => now(),
            ]);
        }

        // Evitar duplicado legacy si quedó otra fila con la URL vieja.
        DB::table('menu')
            ->where('url', self::MENU_LEGACY_URL)
            ->where('id', '!=', $menuId)
            ->delete();

        $rolIds = $this->resolverRolIds(self::ROLES);
        $this->asignarRolesMenu($menuId, $rolIds);
        if ($parentId > 0) {
            $this->asignarRolesMenu($parentId, $rolIds);
        }

        foreach (self::PERMISOS as $permiso) {
            $permisoId = $this->upsertPermiso($permiso['nombre'], $permiso['slug'], $menuId);
            $this->asignarPermisoRoles($permisoId, $rolIds);
        }

        $this->seedTipoempresaClienteDesdeCompras();

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu')->where('id', $menuId)->update([
                'url' => self::MENU_LEGACY_URL,
                'updated_at' => now(),
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function seedTipoempresaClienteDesdeCompras(): void
    {
        if (! Schema::hasTable('tipoempresa_cliente') || ! Schema::hasTable('tipoempresa')) {
            return;
        }

        $filas = DB::table('tipoempresa')->orderBy('id')->get(['codigo', 'nombre']);
        foreach ($filas as $fila) {
            $codigo = trim((string) ($fila->codigo ?? ''));
            if ($codigo === '') {
                continue;
            }
            if (DB::table('tipoempresa_cliente')->where('codigo', $codigo)->exists()) {
                continue;
            }
            DB::table('tipoempresa_cliente')->insert([
                'codigo' => $codigo,
                'nombre' => trim((string) ($fila->nombre ?? '')) ?: "Tipo {$codigo}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = [
            'nombre' => mb_substr($nombre, 0, 50),
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

    /**
     * @param  list<string>  $nombres
     * @return list<int>
     */
    private function resolverRolIds(array $nombres): array
    {
        $ids = [];
        foreach ($nombres as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function asignarRolesMenu(int $menuId, array $rolIds): void
    {
        if ($menuId <= 0) {
            return;
        }
        foreach ($rolIds as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function asignarPermisoRoles(int $permisoId, array $rolIds): void
    {
        if ($permisoId <= 0) {
            return;
        }
        foreach ($rolIds as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }
};

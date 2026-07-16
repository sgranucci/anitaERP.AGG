<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_CLIENTE_VIP_URL = 'caja/cliente-vip';

    private const MENU_PADRE_NOMBRE = 'Tablas de caja';

    /** @var list<string> */
    private const PERMISO_SLUGS = [
        'listar-cliente-vip-caja',
        'crear-cliente-vip-caja',
        'editar-cliente-vip-caja',
        'actualizar-cliente-vip-caja',
        'borrar-cliente-vip-caja',
    ];

    /** @var list<array{nombre: string, like?: string}> */
    private const ROLES = [
        ['nombre' => 'administrador'],
        ['nombre' => 'Enc-tesorería', 'like' => 'Enc-tesorer%'],
        ['nombre' => 'enc-Tesoreria Operativa', 'like' => 'enc-Tesoreria Operativa%'],
        ['nombre' => 'Ger-Tesoreria', 'like' => 'Ger-Tesorer%'],
        ['nombre' => 'Op-tesoreria', 'like' => 'Op-tesorer%'],
        ['nombre' => 'op-Tesoreria Operativa', 'like' => 'op-Tesoreria Operativa%'],
        ['nombre' => 'Sup-tesoreria', 'like' => 'Sup-tesorer%'],
    ];

    public function up(): void
    {
        $moduloCajaId = $this->resolverModuloCajaId();
        $tablasCajaId = $this->resolverTablasCajaId($moduloCajaId);
        if ($tablasCajaId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $tablasCajaId)->max('orden') ?? 0) + 1;
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_CLIENTE_VIP_URL)->value('id') ?? 0);

        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $tablasCajaId,
                'nombre' => 'Clientes VIP',
                'url' => self::MENU_CLIENTE_VIP_URL,
                'orden' => $orden,
                'icono' => 'fa-star',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $tablasCajaId,
                'nombre' => 'Clientes VIP',
                'orden' => $orden,
                'icono' => 'fa-star',
                'updated_at' => now(),
            ]);
        }

        $permisoDefs = [
            ['nombre' => 'Listar clientes VIP caja', 'slug' => 'listar-cliente-vip-caja'],
            ['nombre' => 'Ingresar clientes VIP caja', 'slug' => 'crear-cliente-vip-caja'],
            ['nombre' => 'Editar clientes VIP caja', 'slug' => 'editar-cliente-vip-caja'],
            ['nombre' => 'Actualizar clientes VIP caja', 'slug' => 'actualizar-cliente-vip-caja'],
            ['nombre' => 'Borrar clientes VIP caja', 'slug' => 'borrar-cliente-vip-caja'],
        ];

        $permisoIds = [];
        foreach ($permisoDefs as $row) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $row['slug'])->value('id') ?? 0);
            if ($permisoId === 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $row['nombre'],
                    'slug' => $row['slug'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'menu_id' => $menuId,
                    'nombre' => $row['nombre'],
                    'updated_at' => now(),
                ]);
            }
            $permisoIds[] = $permisoId;
        }

        $menuIdsCadena = array_values(array_unique(array_filter([
            $moduloCajaId,
            $tablasCajaId,
            $menuId,
        ])));

        foreach ($this->resolverRolIds() as $rolId) {
            foreach ($permisoIds as $permisoId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
            foreach ($menuIdsCadena as $mid) {
                if (! DB::table('menu_rol')->where('menu_id', $mid)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert([
                        'menu_id' => $mid,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoIds = DB::table('permiso')->whereIn('slug', self::PERMISO_SLUGS)->pluck('id')->all();
        foreach ($permisoIds as $pid) {
            DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
            DB::table('permiso')->where('id', $pid)->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_CLIENTE_VIP_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverModuloCajaId(): int
    {
        return (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where(function ($q) {
                $q->where('nombre', 'Módulo de Caja')
                    ->orWhere('nombre', 'like', '%Módulo de Caja%');
            })
            ->orderBy('id')
            ->value('id') ?? 104);
    }

    private function resolverTablasCajaId(int $moduloCajaId): int
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', $moduloCajaId)
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 145);
    }

    /**
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        $ids = [];
        foreach (self::ROLES as $def) {
            $id = $this->resolverRolId($def);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array{nombre: string, like?: string}  $def
     */
    private function resolverRolId(array $def): int
    {
        $nombre = (string) ($def['nombre'] ?? '');
        $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        $like = trim((string) ($def['like'] ?? ''));
        if ($like !== '') {
            return (int) (DB::table('rol')->where('nombre', 'like', $like)->orderBy('id')->value('id') ?? 0);
        }

        return 0;
    }
};

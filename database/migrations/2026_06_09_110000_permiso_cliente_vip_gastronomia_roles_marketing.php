<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_CLIENTE_VIP_URL = 'ventas/gastronomia/canjes/cliente-vip';

    /** @var list<string> */
    private const PERMISO_SLUGS = [
        'listar-cliente-vip-gastronomia',
        'crear-cliente-vip-gastronomia',
        'editar-cliente-vip-gastronomia',
        'actualizar-cliente-vip-gastronomia',
        'borrar-cliente-vip-gastronomia',
    ];

    /**
     * Roles de marketing (enc / sup / op). Se resuelven por nombre exacto o alias LIKE.
     *
     * @var list<array{nombre: string, like?: string, crear_si_falta?: bool}>
     */
    private const ROLES_MARKETING = [
        ['nombre' => 'enc-Marketing y CAC', 'like' => 'enc-Marketing%'],
        ['nombre' => 'Sup-Marketing', 'crear_si_falta' => true],
        ['nombre' => 'Op-Marketing', 'like' => 'Op-Marketing%'],
    ];

    public function up(): void
    {
        $rolIds = $this->resolverRolIdsMarketing();
        if ($rolIds === []) {
            return;
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', self::PERMISO_SLUGS)->pluck('id')->all();
        if ($permisoIds === []) {
            return;
        }

        $menuIds = $this->resolverMenuIdsCadena();
        if ($menuIds === []) {
            return;
        }

        foreach ($rolIds as $rolId) {
            foreach ($permisoIds as $permisoId) {
                $pid = (int) $permisoId;
                if ($pid <= 0) {
                    continue;
                }
                if (! DB::table('permiso_rol')->where('permiso_id', $pid)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $pid,
                        'rol_id' => $rolId,
                    ]);
                }
            }

            foreach ($menuIds as $menuId) {
                $mid = (int) $menuId;
                if ($mid <= 0) {
                    continue;
                }
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
        $rolIds = $this->resolverRolIdsMarketing();
        if ($rolIds === []) {
            return;
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', self::PERMISO_SLUGS)->pluck('id')->all();
        $menuIds = $this->resolverMenuIdsCadena();

        foreach ($rolIds as $rolId) {
            if ($permisoIds !== []) {
                DB::table('permiso_rol')
                    ->where('rol_id', $rolId)
                    ->whereIn('permiso_id', $permisoIds)
                    ->delete();
            }
            if ($menuIds !== []) {
                DB::table('menu_rol')
                    ->where('rol_id', $rolId)
                    ->whereIn('menu_id', $menuIds)
                    ->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /**
     * @return list<int>
     */
    private function resolverRolIdsMarketing(): array
    {
        $ids = [];
        foreach (self::ROLES_MARKETING as $def) {
            $id = $this->resolverRolId($def);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array{nombre: string, like?: string, crear_si_falta?: bool}  $def
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
            $id = (int) (DB::table('rol')->where('nombre', 'like', $like)->orderBy('id')->value('id') ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        if (! empty($def['crear_si_falta']) && $nombre !== '') {
            return (int) DB::table('rol')->insertGetId([
                'nombre' => $nombre,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return 0;
    }

    /**
     * Cadena de menú visible: Ventas → Gastronomía → Canjes → Clientes VIP.
     *
     * @return list<int>
     */
    private function resolverMenuIdsCadena(): array
    {
        $ids = [];

        $ventasId = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where(function ($q) {
                $q->where('nombre', 'Módulo de Ventas')
                    ->orWhere('nombre', 'like', '%Módulo de Ventas%');
            })
            ->orderBy('id')
            ->value('id') ?? 0);
        if ($ventasId > 0) {
            $ids[] = $ventasId;
        }

        $gastronomiaId = (int) (DB::table('menu')
            ->where(function ($q) {
                $q->where('nombre', 'Gastronomía')
                    ->orWhere('nombre', 'like', '%Gastronom%');
            })
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
        if ($gastronomiaId > 0) {
            $ids[] = $gastronomiaId;
        }

        $canjesId = (int) (DB::table('menu')
            ->where('nombre', 'Canjes')
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
        if ($canjesId > 0) {
            $ids[] = $canjesId;
        }

        $clienteVipId = (int) (DB::table('menu')->where('url', self::MENU_CLIENTE_VIP_URL)->value('id') ?? 0);
        if ($clienteVipId > 0) {
            $ids[] = $clienteVipId;
        }

        return array_values(array_unique(array_filter($ids)));
    }
};

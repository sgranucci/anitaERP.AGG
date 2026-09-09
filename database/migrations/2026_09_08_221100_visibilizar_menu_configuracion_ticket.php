<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hace visible el menú de configuración de tickets:
 * - Nombre más claro (no se confunde con Configuración general)
 * - Misma asignación de roles que Adm. de Tickets / Informe estadístico
 */
return new class extends Migration
{
    private const MENU_URL = 'ticket/configuracion';

    private const MENU_NOMBRE = 'Configuración de tickets';

    private const MENU_ADM_URL = 'ticket/administracion_ticket';

    private const MENU_INF_URL = 'ticket/informe-estadistico';

    /** @var list<string> */
    private const ROLES_EXTRA = [
        'administrador',
        'Enc-admin',
        'Enc-sistemas',
        'Tecnico de Tecnología',
        'op-Gerencia de Tecnologia',
        'Ger-administracion',
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            return;
        }

        DB::table('menu')->where('id', $menuId)->update([
            'nombre' => self::MENU_NOMBRE,
            'icono' => 'fa-cog',
            'updated_at' => now(),
        ]);

        $rolIds = $this->resolverRolIds($menuId);

        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        $permisoIds = DB::table('permiso')
            ->whereIn('slug', [
                'listar-configuracion-ticket',
                'actualizar-configuracion-ticket',
            ])
            ->pluck('id');

        foreach ($permisoIds as $permisoId) {
            foreach ($rolIds as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu')->where('id', $menuId)->update([
                'nombre' => 'Configuración',
                'updated_at' => now(),
            ]);
        }
        SuitecrmPermiso::flushCachePermisos();
    }

    /**
     * @return list<int>
     */
    private function resolverRolIds(int $menuConfigId): array
    {
        $ids = [];

        foreach ([self::MENU_ADM_URL, self::MENU_INF_URL] as $url) {
            $refMenuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($refMenuId > 0) {
                foreach (DB::table('menu_rol')->where('menu_id', $refMenuId)->pluck('rol_id') as $rolId) {
                    $ids[] = (int) $rolId;
                }
            }
        }

        foreach (self::ROLES_EXTRA as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        // Conserva los que ya tenía
        foreach (DB::table('menu_rol')->where('menu_id', $menuConfigId)->pluck('rol_id') as $rolId) {
            $ids[] = (int) $rolId;
        }

        return array_values(array_unique(array_filter($ids)));
    }
};

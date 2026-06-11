<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'caja/rendicionestacionamiento';

    private const ROLES_TESORERIA = [
        'Op-tesoreria',
        'op-Tesoreria Operativa',
        'Enc-tesorería',
        'Enc-tesoreria',
        'enc-Tesoreria Operativa',
        'Ger-Tesoreria',
        'Sup-tesoreria',
        'Sup-Tesoreria',
    ];

    private const PERMISOS = [
        'listar-rendicion-estacionamiento-caja',
        'crear-rendicion-estacionamiento-caja',
        'editar-rendicion-estacionamiento-caja',
        'actualizar-rendicion-estacionamiento-caja',
        'actualizar-rendicion-estacionamiento-caja-dia',
        'actualizar-rendicion-estacionamiento-caja-encargado',
        'ver-pdf-rendicion-estacionamiento-caja',
        'ver-comprobante-cierre-turno-estacionamiento',
        'listar-cierres-turno-estacionamiento',
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES_TESORERIA)->pluck('id')->all();

        if ($rolIds === []) {
            return;
        }

        $permisoExtras = [
            [
                'nombre' => 'Actualizar rendición estacionamiento caja en el día',
                'slug' => 'actualizar-rendicion-estacionamiento-caja-dia',
                'roles' => ['Op-tesoreria', 'op-Tesoreria Operativa'],
            ],
            [
                'nombre' => 'Actualizar rendición estacionamiento caja sin restricción de fecha',
                'slug' => 'actualizar-rendicion-estacionamiento-caja-encargado',
                'roles' => ['Enc-tesorería', 'Enc-tesoreria', 'enc-Tesoreria Operativa', 'Ger-Tesoreria'],
            ],
            [
                'nombre' => 'Ver PDF rendición estacionamiento caja',
                'slug' => 'ver-pdf-rendicion-estacionamiento-caja',
                'roles' => self::ROLES_TESORERIA,
            ],
        ];

        if ($menuId > 0) {
            foreach ($permisoExtras as $row) {
                $permisoId = $this->upsertPermiso($row['nombre'], $row['slug'], $menuId);
                $this->asignarARoles($permisoId, $row['roles']);
            }
        }

        foreach ($rolIds as $rolId) {
            $rolId = (int) $rolId;
            if ($menuId > 0 && ! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }

            foreach (self::PERMISOS as $slug) {
                $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
                if ($permisoId <= 0) {
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

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);

        if ($permisoId === 0) {
            return (int) DB::table('permiso')->insertGetId([
                'nombre' => $nombre,
                'slug' => $slug,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('permiso')->where('id', $permisoId)->update([
            'menu_id' => $menuId,
            'nombre' => $nombre,
            'updated_at' => now(),
        ]);

        return $permisoId;
    }

    /**
     * @param  list<string>  $nombresRol
     */
    private function asignarARoles(int $permisoId, array $nombresRol): void
    {
        $rolIds = DB::table('rol')->whereIn('nombre', $nombresRol)->pluck('id')->all();

        foreach ($rolIds as $rolId) {
            $rolId = (int) $rolId;
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $slugs = [
            'actualizar-rendicion-estacionamiento-caja-dia',
            'actualizar-rendicion-estacionamiento-caja-encargado',
            'ver-pdf-rendicion-estacionamiento-caja',
        ];

        foreach (DB::table('permiso')->whereIn('slug', $slugs)->pluck('id') as $pid) {
            DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
            DB::table('permiso')->where('id', $pid)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};

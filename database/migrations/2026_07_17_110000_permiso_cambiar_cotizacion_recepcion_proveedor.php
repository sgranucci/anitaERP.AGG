<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permiso especial para cambiar la cotización de una recepción de proveedor confirmada
 * (propaga a recepción, asiento contable ERP, ctamov y recepmov Anita).
 * Asignado a los roles de compras.
 */
return new class extends Migration
{
    private const MENU_RECEPCION = 'stock/recepcion-proveedor';

    private const PERMISO = 'cambiar-cotizacion-recepcion-proveedor';

    /** Roles de compras (más administrador siempre). */
    private const ROLES_COMPRAS = [
        'Enc-compras',
        'Op-Compras',
        'administrador',
    ];

    public function up(): void
    {
        $permisoId = $this->upsertPermiso(
            self::MENU_RECEPCION,
            self::PERMISO,
            'Cambiar cotización en recepción de proveedor confirmada'
        );

        foreach ($this->resolverRolIds(self::ROLES_COMPRAS) as $rolId) {
            $this->asignarPermisoRol($permisoId, $rolId);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertPermiso(string $menuUrl, string $slug, string $nombre): int
    {
        $menuId = (int) (DB::table('menu')->where('url', $menuUrl)->value('id') ?? 0);
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

    /** @param list<string> $nombres */
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

    private function asignarPermisoRol(int $permisoId, int $rolId): void
    {
        if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};

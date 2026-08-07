<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permiso para que el cajero anule tickets canje en estado Pendiente.
 */
return new class extends Migration
{
    private const MENU_URL = 'caja/canjes/generacion';

    private const PERMISO = [
        'nombre' => 'Anular tickets canje caja',
        'slug' => 'anular-ticket-canje-caja',
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
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $permisoId = $this->upsertPermiso(self::PERMISO['nombre'], self::PERMISO['slug'], $menuId);
        foreach ($this->resolverRolIds() as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO['slug'])->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /** @return list<int> */
    private function resolverRolIds(): array
    {
        $ids = [];
        foreach (self::ROLES as $rol) {
            $q = DB::table('rol');
            if (! empty($rol['like'])) {
                $q->where('nombre', 'like', $rol['like']);
            } else {
                $q->where('nombre', $rol['nombre']);
            }
            foreach ($q->pluck('id') as $id) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($id === 0) {
            return (int) DB::table('permiso')->insertGetId([
                'nombre' => $nombre,
                'slug' => $slug,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('permiso')->where('id', $id)->update([
            'nombre' => $nombre,
            'menu_id' => $menuId,
            'updated_at' => now(),
        ]);

        return $id;
    }
};

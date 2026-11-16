<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Separa configurar (alertas/diccionario) y auditar (log global) del listar operativo.
 */
return new class extends Migration
{
    private const MENU_URL = 'direccion/consulta-inteligente';

    /** @var list<array{slug: string, nombre: string}> */
    private const PERMISOS = [
        [
            'slug' => 'configurar-consulta-inteligente',
            'nombre' => 'Configurar consulta inteligente',
        ],
        [
            'slug' => 'auditar-consulta-inteligente',
            'nombre' => 'Auditar consulta inteligente',
        ],
    ];

    /** Roles que reciben configurar + auditar (además de administrador vía can()). */
    private const ROLES_ADMIN = [
        'administrador',
        'Enc-admin',
        'Enc-contaduría',
        'Ger-administracion',
        'Sup-contaduria',
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            return;
        }

        $rolIds = DB::table('rol')
            ->whereIn('nombre', self::ROLES_ADMIN)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        foreach (self::PERMISOS as $perm) {
            $permisoId = $this->upsertPermiso($perm['nombre'], $perm['slug'], $menuId);
            foreach ($rolIds as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        foreach (self::PERMISOS as $perm) {
            $ids = DB::table('permiso')->where('slug', $perm['slug'])->pluck('id');
            if ($ids->isNotEmpty()) {
                DB::table('permiso_rol')->whereIn('permiso_id', $ids)->delete();
                DB::table('permiso')->whereIn('id', $ids)->delete();
            }
        }
        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = [
            'nombre' => $nombre,
            'slug' => $slug,
            'menu_id' => $menuId,
            'updated_at' => now(),
        ];
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, ['created_at' => now()]));
    }
};

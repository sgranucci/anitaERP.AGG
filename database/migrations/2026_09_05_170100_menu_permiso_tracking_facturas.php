<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú + permisos del Tracking de Facturas.
 * Cuelga de «Cuentas a pagar», al lado de Comprobantes, y hereda sus roles.
 */
return new class extends Migration
{
    private const URL = 'compras/tracking-facturas';

    private const NOMBRE = 'Tracking de facturas';

    private const ICONO = 'fa-search-plus';

    private const URL_HERMANO = 'compras/comprobante-proveedor';

    /** @var array<string, string> slug => nombre */
    private const PERMISOS = [
        'listar-tracking-facturas' => 'Listar el tracking de facturas',
        'ver-pdf-tracking-facturas' => 'Ver el PDF de un comprobante desde el tracking',
    ];

    public function up(): void
    {
        $hermano = DB::table('menu')->where('url', self::URL_HERMANO)->first();
        if (! $hermano) {
            return;
        }

        $padreId = (int) $hermano->menu_id;
        $orden = (int) $hermano->orden + 1;

        // Corre un lugar a los hermanos posteriores para dejar el hueco.
        DB::table('menu')
            ->where('menu_id', $padreId)
            ->where('orden', '>=', $orden)
            ->where('url', '!=', self::URL)
            ->update(['orden' => DB::raw('orden + 1'), 'updated_at' => now()]);

        $menuId = $this->upsertMenu($padreId, $orden);

        $rolIds = DB::table('menu_rol')
            ->where('menu_id', $hermano->id)
            ->pluck('rol_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }

        foreach (self::PERMISOS as $slug => $nombre) {
            $permisoId = $this->upsertPermiso($nombre, $slug, $menuId);
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
        $permisoIds = DB::table('permiso')->whereIn('slug', array_keys(self::PERMISOS))->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertMenu(int $padreId, int $orden): int
    {
        $id = (int) (DB::table('menu')->where('url', self::URL)->value('id') ?? 0);
        $payload = [
            'nombre' => self::NOMBRE,
            'url' => self::URL,
            'menu_id' => $padreId,
            'orden' => $orden,
            'icono' => self::ICONO,
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
};

<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Submódulos del circuito de suscripciones: Circuito (manual), Conciliación mensual y
 * Reportes cuelgan junto a Suscripciones, y se suman los permisos de configuración,
 * conciliación e imputación.
 */
return new class extends Migration
{
    private const URL_HERMANO = 'compras/suscripciones';

    /** @var list<array{url: string, nombre: string, icono: string}> */
    private const ENTRADAS = [
        ['url' => 'compras/suscripciones/circuito', 'nombre' => 'Suscripciones · Circuito', 'icono' => 'fa-sitemap'],
        ['url' => 'compras/suscripciones/conciliacion', 'nombre' => 'Suscripciones · Conciliación', 'icono' => 'fa-balance-scale'],
        ['url' => 'compras/suscripciones/reportes', 'nombre' => 'Suscripciones · Reportes', 'icono' => 'fa-bar-chart'],
    ];

    /** @var array<string, string> slug => nombre */
    private const PERMISOS = [
        'configurar-suscripcion' => 'Configurar aprobadores y tarjetas de suscripciones',
        'conciliar-suscripcion' => 'Conciliar el resumen de tarjeta corporativa',
        'imputar-suscripcion' => 'Imputar cargos de suscripción en Ingresos y egresos',
        'reportar-suscripcion' => 'Ver reportes de suscripciones',
    ];

    public function up(): void
    {
        $hermano = DB::table('menu')->where('url', self::URL_HERMANO)->first();
        if (! $hermano) {
            return;
        }

        $padreId = (int) $hermano->menu_id;
        $rolIds = DB::table('menu_rol')
            ->where('menu_id', $hermano->id)
            ->pluck('rol_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $orden = (int) $hermano->orden;
        $urlsNuevas = array_column(self::ENTRADAS, 'url');

        DB::table('menu')
            ->where('menu_id', $padreId)
            ->where('orden', '>', $orden)
            ->whereNotIn('url', $urlsNuevas)
            ->update(['orden' => DB::raw('orden + '.count(self::ENTRADAS)), 'updated_at' => now()]);

        $menuIds = [];
        foreach (self::ENTRADAS as $indice => $entrada) {
            $menuIds[] = $this->upsertMenu($entrada, $padreId, $orden + $indice + 1);
        }

        foreach ($menuIds as $menuId) {
            foreach ($rolIds as $rolId) {
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
                }
            }
        }

        // Los permisos nuevos cuelgan del menú principal de Suscripciones.
        foreach (self::PERMISOS as $slug => $nombre) {
            $permisoId = $this->upsertPermiso($nombre, $slug, (int) $hermano->id);
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

        $menuIds = DB::table('menu')->whereIn('url', array_column(self::ENTRADAS, 'url'))->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menu')->whereIn('id', $menuIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /** @param array{url: string, nombre: string, icono: string} $entrada */
    private function upsertMenu(array $entrada, int $padreId, int $orden): int
    {
        $payload = [
            'nombre' => $entrada['nombre'],
            'url' => $entrada['url'],
            'menu_id' => $padreId,
            'orden' => $orden,
            'icono' => $entrada['icono'],
            'updated_at' => now(),
        ];

        $id = (int) (DB::table('menu')->where('url', $entrada['url'])->value('id') ?? 0);
        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId(array_merge($payload, ['created_at' => now()]));
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $payload = ['nombre' => $nombre, 'slug' => $slug, 'menu_id' => $menuId, 'updated_at' => now()];

        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, ['created_at' => now()]));
    }
};

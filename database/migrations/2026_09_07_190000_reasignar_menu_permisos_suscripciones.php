<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reasigna los permisos de Suscripciones a la hoja de menú correcta.
 *
 * Quedaban todos colgados de "Suscripciones activas" porque el alta de
 * submódulos usó el menu_id del listado. Además parte configurar-suscripcion
 * (aprobadores + tarjetas) en un permiso por pantalla.
 */
return new class extends Migration
{
    /** slug => url de menú destino */
    private const REASIGNAR = [
        'listar-suscripcion' => 'compras/suscripciones',
        'crear-suscripcion' => 'compras/suscripciones',
        'aprobar-suscripcion' => 'compras/suscripciones',
        'conciliar-suscripcion' => 'compras/suscripciones/conciliacion',
        'imputar-suscripcion' => 'compras/suscripciones/conciliacion',
        'reportar-suscripcion' => 'compras/suscripciones/reportes',
    ];

    private const SLUG_CONFIG_VIEJO = 'configurar-suscripcion';

    /** @var array<string, array{nombre: string, url: string}> */
    private const CONFIG_NUEVOS = [
        'configurar-aprobadores-suscripcion' => [
            'nombre' => 'Configurar aprobadores de suscripciones',
            'url' => 'compras/suscripciones/aprobadores',
        ],
        'configurar-tarjetas-suscripcion' => [
            'nombre' => 'Configurar tarjetas de suscripciones',
            'url' => 'compras/suscripciones/tarjetas',
        ],
    ];

    public function up(): void
    {
        foreach (self::REASIGNAR as $slug => $url) {
            $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($menuId <= 0) {
                continue;
            }
            DB::table('permiso')->where('slug', $slug)->update([
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);
        }

        $viejo = DB::table('permiso')->where('slug', self::SLUG_CONFIG_VIEJO)->first();
        if ($viejo) {
            $rolIds = DB::table('permiso_rol')
                ->where('permiso_id', $viejo->id)
                ->pluck('rol_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            foreach (self::CONFIG_NUEVOS as $slug => $meta) {
                $menuId = (int) (DB::table('menu')->where('url', $meta['url'])->value('id') ?? 0);
                if ($menuId <= 0) {
                    continue;
                }

                $permisoId = $this->upsertPermiso($meta['nombre'], $slug, $menuId);
                foreach ($rolIds as $rolId) {
                    if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                        DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                    }
                }
            }

            DB::table('permiso_rol')->where('permiso_id', $viejo->id)->delete();
            DB::table('permiso')->where('id', $viejo->id)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $listadoId = (int) (DB::table('menu')->where('url', 'compras/suscripciones')->value('id') ?? 0);
        if ($listadoId <= 0) {
            return;
        }

        $rolIds = [];
        foreach (array_keys(self::CONFIG_NUEVOS) as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId <= 0) {
                continue;
            }
            foreach (DB::table('permiso_rol')->where('permiso_id', $permisoId)->pluck('rol_id') as $rolId) {
                $rolIds[(int) $rolId] = true;
            }
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        $configId = $this->upsertPermiso(
            'Configurar aprobadores y tarjetas de suscripciones',
            self::SLUG_CONFIG_VIEJO,
            $listadoId
        );
        foreach (array_keys($rolIds) as $rolId) {
            if ($rolId > 0 && ! DB::table('permiso_rol')->where('permiso_id', $configId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $configId, 'rol_id' => $rolId]);
            }
        }

        foreach (array_keys(self::REASIGNAR) as $slug) {
            DB::table('permiso')->where('slug', $slug)->update([
                'menu_id' => $listadoId,
                'updated_at' => now(),
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
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

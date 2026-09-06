<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Anida las pantallas de Suscripciones bajo un submenu y saca Circuito del menú.
 *
 * Circuito es documentación: vive en el manual / Centro de ayuda, no como ítem
 * del sidebar. Tarjetas y Aprobadores van dentro del mismo árbol.
 */
return new class extends Migration
{
    private const URL_LISTADO = 'compras/suscripciones';

    private const URL_CIRCUITO = 'compras/suscripciones/circuito';

    /** Hojas que cuelgan del submenu (orden relativo). */
    private const HIJOS = [
        'compras/suscripciones' => ['nombre' => 'Suscripciones activas', 'icono' => 'fa-list', 'orden' => 1],
        'compras/suscripciones/aprobadores' => ['nombre' => 'Aprobadores', 'icono' => 'fa-user-circle-o', 'orden' => 2],
        'compras/suscripciones/tarjetas' => ['nombre' => 'Tarjetas', 'icono' => 'fa-credit-card', 'orden' => 3],
        'compras/suscripciones/conciliacion' => ['nombre' => 'Conciliación', 'icono' => 'fa-balance-scale', 'orden' => 4],
        'compras/suscripciones/reportes' => ['nombre' => 'Reportes', 'icono' => 'fa-bar-chart', 'orden' => 5],
    ];

    public function up(): void
    {
        $listado = DB::table('menu')->where('url', self::URL_LISTADO)->first();
        if (! $listado) {
            return;
        }

        $padreComprasId = (int) $listado->menu_id;
        $ordenPadre = (int) $listado->orden;

        // Contenedor del submenu: misma posición que tenía el listado plano.
        $submenuId = $this->upsertMenu([
            'nombre' => 'Suscripciones',
            'url' => '#',
            'menu_id' => $padreComprasId,
            'orden' => $ordenPadre,
            'icono' => 'fa-refresh',
        ]);

        $rolIds = DB::table('menu_rol')
            ->where('menu_id', $listado->id)
            ->pluck('rol_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $submenuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $submenuId, 'rol_id' => $rolId]);
            }
        }

        foreach (self::HIJOS as $url => $meta) {
            $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($id <= 0) {
                continue;
            }

            DB::table('menu')->where('id', $id)->update([
                'menu_id' => $submenuId,
                'nombre' => $meta['nombre'],
                'orden' => $meta['orden'],
                'icono' => $meta['icono'],
                'updated_at' => now(),
            ]);
        }

        // Circuito: documentación, no menú.
        $circuitoId = (int) (DB::table('menu')->where('url', self::URL_CIRCUITO)->value('id') ?? 0);
        if ($circuitoId > 0) {
            DB::table('menu_rol')->where('menu_id', $circuitoId)->delete();
            DB::table('menu')->where('id', $circuitoId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $submenu = DB::table('menu')
            ->where('nombre', 'Suscripciones')
            ->where('url', '#')
            ->where('menu_id', '>', 0)
            ->first();

        if (! $submenu) {
            return;
        }

        $comprasId = (int) $submenu->menu_id;
        $ordenBase = (int) $submenu->orden;

        $i = 0;
        foreach (array_keys(self::HIJOS) as $url) {
            DB::table('menu')->where('url', $url)->update([
                'menu_id' => $comprasId,
                'orden' => $ordenBase + $i,
                'updated_at' => now(),
            ]);
            $i++;
        }

        // Restaura Circuito como hermano (sin roles: down de menú suele ser parcial).
        if (! DB::table('menu')->where('url', self::URL_CIRCUITO)->exists()) {
            DB::table('menu')->insert([
                'nombre' => 'Suscripciones · Circuito',
                'url' => self::URL_CIRCUITO,
                'menu_id' => $comprasId,
                'orden' => $ordenBase + $i,
                'icono' => 'fa-sitemap',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu_rol')->where('menu_id', $submenu->id)->delete();
        DB::table('menu')->where('id', $submenu->id)->delete();

        SuitecrmPermiso::flushCachePermisos();
    }

    /** @param array{nombre: string, url: string, menu_id: int, orden: int, icono: string} $entrada */
    private function upsertMenu(array $entrada): int
    {
        $existente = DB::table('menu')
            ->where('nombre', $entrada['nombre'])
            ->where('url', $entrada['url'])
            ->where('menu_id', $entrada['menu_id'])
            ->first();

        $payload = [
            'nombre' => $entrada['nombre'],
            'url' => $entrada['url'],
            'menu_id' => $entrada['menu_id'],
            'orden' => $entrada['orden'],
            'icono' => $entrada['icono'],
            'updated_at' => now(),
        ];

        if ($existente) {
            DB::table('menu')->where('id', $existente->id)->update($payload);

            return (int) $existente->id;
        }

        return (int) DB::table('menu')->insertGetId(array_merge($payload, ['created_at' => now()]));
    }
};

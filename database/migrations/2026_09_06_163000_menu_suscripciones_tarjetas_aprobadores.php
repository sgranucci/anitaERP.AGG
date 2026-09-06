<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aprobadores y Tarjetas al menú.
 *
 * Las dos pantallas ya existían pero no colgaban de ningún lado: a Tarjetas solo se
 * llegaba tecleando la URL, y sin tarjetas cargadas el cruce del resumen por últimos
 * cuatro dígitos no encuentra nada y la imputación no tiene cuenta contra la que asentar.
 */
return new class extends Migration
{
    private const URL_HERMANO = 'compras/suscripciones';

    /** Van antes de Conciliación: primero se configura, después se concilia. */
    private const ENTRADAS = [
        ['url' => 'compras/suscripciones/aprobadores', 'nombre' => 'Suscripciones · Aprobadores', 'icono' => 'fa-user-circle-o'],
        ['url' => 'compras/suscripciones/tarjetas', 'nombre' => 'Suscripciones · Tarjetas', 'icono' => 'fa-credit-card'],
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

        // Se insertan justo después del Circuito, corriendo lo que ya estaba.
        $ordenCircuito = (int) (DB::table('menu')
            ->where('url', 'compras/suscripciones/circuito')
            ->value('orden') ?? $hermano->orden);

        $urlsNuevas = array_column(self::ENTRADAS, 'url');

        DB::table('menu')
            ->where('menu_id', $padreId)
            ->where('orden', '>', $ordenCircuito)
            ->whereNotIn('url', $urlsNuevas)
            ->update(['orden' => DB::raw('orden + '.count(self::ENTRADAS)), 'updated_at' => now()]);

        $menuIds = [];
        foreach (self::ENTRADAS as $indice => $entrada) {
            $menuIds[] = $this->upsertMenu($entrada, $padreId, $ordenCircuito + $indice + 1);
        }

        foreach ($menuIds as $menuId) {
            foreach ($rolIds as $rolId) {
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
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
};

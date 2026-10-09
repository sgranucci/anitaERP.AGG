<?php

declare(strict_types=1);

namespace App\Support\Listado;

use App\Models\Listado\ListadoVista;
use App\Support\SuitecrmPermiso;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bookmark de vista guardada → ítem de menú (hermano del listado origen).
 */
final class ListadoVistaMenuSupport
{
    /**
     * @var array<string, array{url_base: string, icono: string, etiqueta_padre: string}>
     */
    private const RECURSOS = [
        'ventas.cliente' => [
            'url_base' => 'ventas/cliente',
            'icono' => 'fa-bookmark',
            'etiqueta_padre' => 'Clientes',
        ],
        'compras.proveedor' => [
            'url_base' => 'compras/proveedor',
            'icono' => 'fa-bookmark',
            'etiqueta_padre' => 'Proveedores',
        ],
        'sueldos.empleado' => [
            'url_base' => 'sueldos/empleado',
            'icono' => 'fa-bookmark',
            'etiqueta_padre' => 'Empleados',
        ],
        'logistica.solicitud' => [
            'url_base' => 'logistica/solicitud',
            'icono' => 'fa-bookmark',
            'etiqueta_padre' => 'Solicitudes',
        ],
        'caja.ingresoegreso' => [
            'url_base' => 'caja/ingresoegreso',
            'icono' => 'fa-bookmark',
            'etiqueta_padre' => 'Ingresos y egresos',
        ],
    ];

    public static function columnaMenuDisponible(): bool
    {
        return Schema::hasTable('listado_vista')
            && Schema::hasColumn('listado_vista', 'menu_id');
    }

    public static function soportaRecurso(string $recurso): bool
    {
        return isset(self::RECURSOS[$recurso]);
    }

    /**
     * Crea / actualiza / quita el atajo de menú según el checkbox del formulario.
     */
    public static function sincronizar(ListadoVista $vista, bool $crearEnMenu): void
    {
        if (! self::columnaMenuDisponible() || ! self::soportaRecurso((string) $vista->recurso)) {
            return;
        }

        if ($crearEnMenu) {
            self::crearOActualizar($vista);
        } else {
            self::quitar($vista);
        }
    }

    public static function quitarAlEliminarVista(ListadoVista $vista): void
    {
        if (! self::columnaMenuDisponible()) {
            return;
        }
        self::quitar($vista);
    }

    private static function crearOActualizar(ListadoVista $vista): void
    {
        $cfg = self::RECURSOS[(string) $vista->recurso];
        $url = $cfg['url_base'].'?vista_id='.$vista->id;
        $nombre = mb_substr('Vista: '.trim((string) $vista->nombre), 0, 80);
        $menuId = (int) ($vista->menu_id ?? 0);

        if ($menuId > 0) {
            $existente = DB::table('menu')->where('id', $menuId)->first();
            if ($existente) {
                DB::table('menu')->where('id', $menuId)->update([
                    'nombre' => $nombre,
                    'url' => $url,
                    'icono' => $cfg['icono'],
                    'updated_at' => now(),
                ]);
                SuitecrmPermiso::flushCachePermisos();

                return;
            }
            $vista->menu_id = null;
        }

        $hermano = DB::table('menu')->where('url', $cfg['url_base'])->orderBy('id')->first();
        if (! $hermano) {
            return;
        }

        $padreId = (int) $hermano->menu_id;
        $orden = (int) DB::table('menu')->where('menu_id', $padreId)->max('orden') + 1;

        $nuevoId = (int) DB::table('menu')->insertGetId([
            'nombre' => $nombre,
            'url' => $url,
            'menu_id' => $padreId,
            'orden' => $orden,
            'icono' => $cfg['icono'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rolIds = DB::table('menu_rol')
            ->where('menu_id', $hermano->id)
            ->pluck('rol_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        foreach ($rolIds as $rolId) {
            $ya = DB::table('menu_rol')
                ->where('menu_id', $nuevoId)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $ya) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $nuevoId,
                    'rol_id' => $rolId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $vista->menu_id = $nuevoId;
        $vista->save();

        SuitecrmPermiso::flushCachePermisos();
    }

    private static function quitar(ListadoVista $vista): void
    {
        $menuId = (int) ($vista->menu_id ?? 0);
        if ($menuId <= 0) {
            return;
        }

        DB::table('menu_rol')->where('menu_id', $menuId)->delete();
        DB::table('menu')->where('id', $menuId)->delete();

        $vista->menu_id = null;
        if ($vista->exists) {
            $vista->save();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
}

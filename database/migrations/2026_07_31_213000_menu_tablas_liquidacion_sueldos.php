<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Separa maestros de liquidación del submenu "Tablas de Sueldos".
 * Nuevo submenu: "Tablas de liquidación".
 */
return new class extends Migration
{
    private const MENU_MODULO = 'Módulo Sueldos y Jornales';

    private const SUBMENU_TABLAS = 'Tablas de Sueldos';

    private const SUBMENU_LIQ = 'Tablas de liquidación';

    /** URLs que pasan a Tablas de liquidación (orden deseado). */
    private const HIJOS_LIQUIDACION = [
        'sueldos/concepto',
        'sueldos/grupo-concepto',
        'sueldos/acumulador',
        'sueldos/parametro',
        'sueldos/ganancia-linea',
        'sueldos/ganancia-escala',
        'sueldos/ganancia-deduccion',
    ];

    /** Orden restante en Tablas de Sueldos (maestros de legajo). */
    private const HIJOS_TABLAS_LEGAJO = [
        'sueldos/nombrebase',
        'sueldos/categoria',
        'sueldos/obrasocial',
        'sueldos/sindicato',
        'sueldos/fallocaja',
        'sueldos/agrupamiento',
        'sueldos/lugartrabajo',
        'sueldos/motivoegreso',
        'sueldos/art',
        'sueldos/vacacion',
        'sueldos/tipo-ausencia',
    ];

    /** Orden de submenús bajo el módulo. */
    private const ORDEN_SUBMENUS = [
        'Personal' => 1,
        'Liquidación' => 2,
        self::SUBMENU_LIQ => 3,
        self::SUBMENU_TABLAS => 4,
        'Reportes de Sueldos' => 5,
        'Indumentaria' => 6,
    ];

    public function up(): void
    {
        $moduloId = (int) (DB::table('menu')->where('nombre', self::MENU_MODULO)->where('menu_id', 0)->value('id') ?? 0);
        if ($moduloId === 0) {
            return;
        }

        $tablasId = (int) (DB::table('menu')->where('nombre', self::SUBMENU_TABLAS)->where('menu_id', $moduloId)->value('id') ?? 0);
        if ($tablasId === 0) {
            return;
        }

        $liqTablasId = (int) (DB::table('menu')->where('nombre', self::SUBMENU_LIQ)->where('menu_id', $moduloId)->value('id') ?? 0);
        if ($liqTablasId === 0) {
            $liqTablasId = (int) DB::table('menu')->insertGetId([
                'nombre' => self::SUBMENU_LIQ,
                'url' => '#',
                'menu_id' => $moduloId,
                'orden' => self::ORDEN_SUBMENUS[self::SUBMENU_LIQ],
                'icono' => 'fa-calculator',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $liqTablasId)->update([
                'orden' => self::ORDEN_SUBMENUS[self::SUBMENU_LIQ],
                'icono' => 'fa-calculator',
                'updated_at' => now(),
            ]);
        }

        // Roles que ya ven Tablas de Sueldos → también ven el nuevo submenu.
        $rolIds = DB::table('menu_rol')->where('menu_id', $tablasId)->pluck('rol_id')
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($rolIds === []) {
            $rolIds = DB::table('rol')
                ->where('nombre', 'administrador')
                ->orWhere('nombre', 'like', '%apital%umano%')
                ->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        }
        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $liqTablasId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $liqTablasId, 'rol_id' => $rolId]);
            }
        }

        $orden = 0;
        foreach (self::HIJOS_LIQUIDACION as $url) {
            $orden++;
            $afectadas = DB::table('menu')->where('url', $url)->update([
                'menu_id' => $liqTablasId,
                'orden' => $orden,
                'updated_at' => now(),
            ]);
            if ($afectadas === 0) {
                // URL alternativa con/sin guion final no aplica; solo log implícito.
            }
        }

        $orden = 0;
        foreach (self::HIJOS_TABLAS_LEGAJO as $url) {
            $orden++;
            DB::table('menu')->where('url', $url)->update([
                'menu_id' => $tablasId,
                'orden' => $orden,
                'updated_at' => now(),
            ]);
        }

        foreach (self::ORDEN_SUBMENUS as $nombre => $ordenSub) {
            DB::table('menu')
                ->where('menu_id', $moduloId)
                ->where('nombre', $nombre)
                ->update(['orden' => $ordenSub, 'updated_at' => now()]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $moduloId = (int) (DB::table('menu')->where('nombre', self::MENU_MODULO)->where('menu_id', 0)->value('id') ?? 0);
        $tablasId = (int) (DB::table('menu')->where('nombre', self::SUBMENU_TABLAS)->where('menu_id', $moduloId)->value('id') ?? 0);
        $liqTablasId = (int) (DB::table('menu')->where('nombre', self::SUBMENU_LIQ)->where('menu_id', $moduloId)->value('id') ?? 0);
        if ($tablasId === 0 || $liqTablasId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $tablasId)->max('orden') ?? 0);
        foreach (self::HIJOS_LIQUIDACION as $url) {
            $orden++;
            DB::table('menu')->where('url', $url)->update([
                'menu_id' => $tablasId,
                'orden' => $orden,
                'updated_at' => now(),
            ]);
        }

        DB::table('menu_rol')->where('menu_id', $liqTablasId)->delete();
        DB::table('menu')->where('id', $liqTablasId)->delete();

        // Restaurar orden aproximado de submenús previos.
        $ordenViejo = [
            'Personal' => 1,
            self::SUBMENU_TABLAS => 2,
            'Reportes de Sueldos' => 3,
            'Indumentaria' => 4,
            'Liquidación' => 5,
        ];
        foreach ($ordenViejo as $nombre => $ordenSub) {
            DB::table('menu')
                ->where('menu_id', $moduloId)
                ->where('nombre', $nombre)
                ->update(['orden' => $ordenSub, 'updated_at' => now()]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};

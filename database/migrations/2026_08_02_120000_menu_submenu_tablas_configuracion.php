<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrupa bajo Configuración → «Tablas de Configuración» los maestros geográficos,
 * fiscales y de compra que hoy cuelgan sueltos del módulo.
 */
return new class extends Migration
{
    private const PADRE_NOMBRE = 'Tablas de Configuración';

    private const PADRE_ICONO = 'fa-table';

    /** url => [nombre, orden]. */
    private const HIJOS = [
        'configuracion/sala' => ['Salas', 1],
        'configuracion/moneda' => ['Monedas', 2],
        'configuracion/pais' => ['Países', 3],
        'configuracion/localidad' => ['Localidades', 4],
        'configuracion/provincia' => ['Provincias', 5],
        'configuracion/tipodocumento' => ['Tipos de documento', 6],
        'configuracion/condicionIIBB' => ['Condiciones IIBB', 7],
        'configuracion/condicioniva' => ['Condiciones IVA', 8],
        'configuracion/actividad_arca' => ['Actividad ARCA', 9],
        'configuracion/oficinacompra' => ['Oficina de compra', 10],
        'configuracion/periodicidadcompra' => ['Periodicidad compra', 11],
    ];

    /** Restauración aproximada al estado previo (nombre + orden bajo Configuración). */
    private const HIJOS_DOWN = [
        'configuracion/sala' => ['Salas', 3],
        'configuracion/moneda' => ['Monedas', 4],
        'configuracion/pais' => ['Paises', 7],
        'configuracion/provincia' => ['Provincias', 8],
        'configuracion/localidad' => ['Localidades', 9],
        'configuracion/condicioniva' => ['Condiciones de iva', 10],
        'configuracion/condicionIIBB' => ['Condiciones de IIBB', 11],
        'configuracion/tipodocumento' => ['Tipo de documento', 12],
        'configuracion/actividad_arca' => ['Actividad ARCA', 14],
        'configuracion/oficinacompra' => ['Oficina de compras', 15],
        'configuracion/periodicidadcompra' => ['Period. de compra', 16],
    ];

    public function up(): void
    {
        $configId = $this->resolverMenuConfiguracionId();
        if ($configId === 0) {
            return;
        }

        $ordenPadre = (int) (DB::table('menu')->where('url', 'configuracion/sala')->value('orden') ?? 3);

        $padreId = (int) (DB::table('menu')
            ->where('menu_id', $configId)
            ->where('nombre', self::PADRE_NOMBRE)
            ->where('url', '#')
            ->value('id') ?? 0);

        if ($padreId === 0) {
            $padreId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $configId,
                'nombre' => self::PADRE_NOMBRE,
                'url' => '#',
                'orden' => $ordenPadre,
                'icono' => self::PADRE_ICONO,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $padreId)->update([
                'menu_id' => $configId,
                'nombre' => self::PADRE_NOMBRE,
                'url' => '#',
                'orden' => $ordenPadre,
                'icono' => self::PADRE_ICONO,
                'updated_at' => now(),
            ]);
        }

        $hijoIds = [];
        foreach (self::HIJOS as $url => [$nombre, $orden]) {
            $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($id === 0) {
                continue;
            }
            DB::table('menu')->where('id', $id)->update([
                'menu_id' => $padreId,
                'nombre' => $nombre,
                'orden' => $orden,
                'updated_at' => now(),
            ]);
            $hijoIds[] = $id;
        }

        $rolIds = collect();
        foreach ($hijoIds as $hijoId) {
            $rolIds = $rolIds->merge(
                DB::table('menu_rol')->where('menu_id', $hijoId)->pluck('rol_id')
            );
        }
        $rolIds = $rolIds->map(fn ($id) => (int) $id)->unique()->values();

        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $padreId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $padreId, 'rol_id' => $rolId]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $configId = $this->resolverMenuConfiguracionId();
        $padreId = (int) (DB::table('menu')
            ->where('menu_id', $configId)
            ->where('nombre', self::PADRE_NOMBRE)
            ->where('url', '#')
            ->value('id') ?? 0);

        foreach (self::HIJOS_DOWN as $url => [$nombre, $orden]) {
            $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($id === 0) {
                continue;
            }
            DB::table('menu')->where('id', $id)->update([
                'menu_id' => $configId,
                'nombre' => $nombre,
                'orden' => $orden,
                'updated_at' => now(),
            ]);
        }

        if ($padreId > 0) {
            DB::table('menu_rol')->where('menu_id', $padreId)->delete();
            DB::table('menu')->where('id', $padreId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverMenuConfiguracionId(): int
    {
        $id = (int) (DB::table('menu')->where('url', 'configuracion/empresa')->value('menu_id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        foreach (['Configuración', 'Módulo Configuración', 'Configuracion'] as $nombre) {
            $id = (int) (DB::table('menu')->where('nombre', $nombre)->where('menu_id', 0)->value('id') ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return 33;
    }
};

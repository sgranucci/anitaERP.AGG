<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrupa parametrizaciones de todos los módulos bajo:
 * Configuración → Configuración por módulo → {Contable|Compras|Caja|Stock|Ventas|Sueldos}.
 *
 * No cambia URLs, rutas ni controllers. No mueve procesos operativos
 * (aperturas/cierres, aprobaciones, jornadas, etc.).
 * Recrea entradas de menú que hoy solo se abren por botón.
 */
return new class extends Migration
{
    private const GRUPO_NOMBRE = 'Configuración por módulo';

    private const GRUPO_ICONO = 'fa-cogs';

    private const GRUPO_URL = '#configuracion-por-modulo';

    /** @var array<string, array{nombre: string, icono: string, orden: int}> */
    private const MODULOS = [
        'contable' => ['nombre' => 'Contable', 'icono' => 'fa-book', 'orden' => 1],
        'compras' => ['nombre' => 'Compras', 'icono' => 'fa-cart-plus', 'orden' => 2],
        'caja' => ['nombre' => 'Caja', 'icono' => 'fa-building', 'orden' => 3],
        'stock' => ['nombre' => 'Stock', 'icono' => 'fa-cubes', 'orden' => 4],
        'ventas' => ['nombre' => 'Ventas', 'icono' => 'fa-shopping-bag', 'orden' => 5],
        'sueldos' => ['nombre' => 'Sueldos', 'icono' => 'fa-money', 'orden' => 6],
    ];

    /**
     * Menús existentes a reubicar.
     * url => [moduloKey, nombre, orden, padreOriginalId|null, padreOriginalUrl|null, padreOriginalNombre|null, ordenOriginal]
     *
     * @var array<string, array{0: string, 1: string, 2: int, 3: ?int, 4: ?string, 5: ?string, 6: int}>
     */
    private const MOVER = [
        'contable/configuracion-asiento' => ['contable', 'Configuración asientos', 1, 353, null, 'Aprobaciones y períodos', 4],
        'contable/tipoasiento' => ['contable', 'Tipos de asiento', 2, 148, null, 'Tablas Contable', 3],
        'contable/cuentas-automaticas' => ['contable', 'Cuentas automáticas', 3, 43, null, 'Módulo Contable', 7],
        'contable/sicore-config' => ['contable', 'Configuración SICORE', 4, 399, null, 'Presentaciones ARCA', 4],
        'contable/ingresos-brutos-config' => ['contable', 'Configuración IIBB', 5, 399, null, 'Presentaciones ARCA', 6],
        'caja/flash/parametro' => ['caja', 'Parámetros flash', 1, 395, null, 'Flash', 3],
        'caja/bingo/configuracion-puntoventa' => ['caja', 'Config. terminal bingo', 2, 382, null, 'Bingo', 5],
        'caja/estacionamiento/configuracion-puntoventa' => ['caja', 'Config. punto de venta estacionamiento', 3, 456, null, 'Tablas Estacionamiento', 7],
        'stock/configuracion-prestamo' => ['stock', 'Config. préstamos', 1, 260, null, 'Módulo de Préstamos', 2],
        'ventas/configuracion-puntoventa-gastronomia' => ['ventas', 'Config. terminales gastronomía', 1, 369, null, 'Tablas Gastronomía', 6],
        'ventas/gastronomia/viandas/configuracion-terminal' => ['ventas', 'Terminales viandas', 2, 372, null, 'Viandas', 4],
        'sueldos/parametro' => ['sueldos', 'Parámetros de liquidación', 1, 475, null, 'Tablas de liquidación', 4],
    ];

    /**
     * Opciones a crear (antes solo-botón).
     * url => [moduloKey, nombre, orden, icono, slugsPermiso, urlMenuProcesoFallback]
     *
     * @var array<string, array{0: string, 1: string, 2: int, 3: string, 4: list<string>, 5: string}>
     */
    private const CREAR = [
        'contable/suss-config' => [
            'contable',
            'Configuración SUSS',
            6,
            'fa-cog',
            ['listar-suss-config', 'crear-suss-config', 'editar-suss-config', 'actualizar-suss-config', 'eliminar-suss-config'],
            'contable/suss',
        ],
        'compras/configuracion-comprobante-proveedor' => [
            'compras',
            'Config. comprobante proveedor',
            1,
            'fa-cog',
            ['editar-configuracion-comprobante-proveedor', 'actualizar-configuracion-comprobante-proveedor'],
            'compras/comprobante-proveedor',
        ],
        'compras/configuracion-propuesta-pago' => [
            'compras',
            'Config. propuesta de pagos',
            2,
            'fa-cog',
            ['editar-configuracion-propuesta-pago', 'actualizar-configuracion-propuesta-pago'],
            'compras/propuesta-pago',
        ],
        'configuracion/recepcion-proveedor' => [
            'stock',
            'Config. recepción proveedores',
            2,
            'fa-cog',
            ['editar-configuracion-recepcion-proveedor', 'actualizar-configuracion-recepcion-proveedor'],
            'stock/recepcion-proveedor',
        ],
        'sueldos/indumentaria/configuracion' => [
            'sueldos',
            'Configuración indumentaria',
            2,
            'fa-cogs',
            ['ver-configuracion-indumentaria', 'editar-configuracion-indumentaria'],
            'sueldos/prenda',
        ],
    ];

    public function up(): void
    {
        $configId = $this->resolverMenuConfiguracionId();
        if ($configId === 0) {
            return;
        }

        $grupoId = $this->asegurarNodo(
            $configId,
            self::GRUPO_NOMBRE,
            self::GRUPO_URL,
            $this->siguienteOrden($configId),
            self::GRUPO_ICONO,
        );

        $moduloIds = [];
        foreach (self::MODULOS as $key => $meta) {
            $moduloIds[$key] = $this->asegurarNodo(
                $grupoId,
                $meta['nombre'],
                '#config-modulo-'.$key,
                $meta['orden'],
                $meta['icono'],
            );
        }

        $hijoIdsPorModulo = array_fill_keys(array_keys(self::MODULOS), []);

        foreach (self::MOVER as $url => [$moduloKey, $nombre, $orden]) {
            $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($menuId === 0 || ! isset($moduloIds[$moduloKey])) {
                continue;
            }
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $moduloIds[$moduloKey],
                'nombre' => $nombre,
                'orden' => $orden,
                'updated_at' => now(),
            ]);
            $hijoIdsPorModulo[$moduloKey][] = $menuId;
        }

        foreach (self::CREAR as $url => [$moduloKey, $nombre, $orden, $icono, $slugs, $urlProceso]) {
            if (! isset($moduloIds[$moduloKey])) {
                continue;
            }
            $menuId = $this->asegurarHoja(
                $moduloIds[$moduloKey],
                $nombre,
                $url,
                $orden,
                $icono,
            );
            if ($menuId === 0) {
                continue;
            }
            DB::table('permiso')->whereIn('slug', $slugs)->update([
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);
            $rolIds = $this->rolesConAlgunPermiso($slugs);
            if ($rolIds->isEmpty()) {
                $rolIds = $this->rolesDelMenuUrl($urlProceso);
            }
            $this->asignarRolesMenu($menuId, $rolIds);
            $hijoIdsPorModulo[$moduloKey][] = $menuId;
        }

        // Propagar menu_rol: hijos → módulo → grupo → Configuración.
        $rolesGrupo = collect();
        foreach ($hijoIdsPorModulo as $moduloKey => $hijoIds) {
            if ($hijoIds === [] || ! isset($moduloIds[$moduloKey])) {
                // Sin hijos en este entorno: no dejar submenú vacío visible.
                $this->eliminarNodoSiVacio($moduloIds[$moduloKey] ?? 0);
                unset($moduloIds[$moduloKey]);
                continue;
            }
            $rolesModulo = collect();
            foreach ($hijoIds as $hijoId) {
                $rolesModulo = $rolesModulo->merge(
                    DB::table('menu_rol')->where('menu_id', $hijoId)->pluck('rol_id')
                );
            }
            $rolesModulo = $rolesModulo->map(fn ($id) => (int) $id)->unique()->values();
            $this->asignarRolesMenu($moduloIds[$moduloKey], $rolesModulo);
            $rolesGrupo = $rolesGrupo->merge($rolesModulo);
        }

        $rolesGrupo = $rolesGrupo->map(fn ($id) => (int) $id)->unique()->values();
        if ($rolesGrupo->isEmpty()) {
            $this->eliminarNodoSiVacio($grupoId);
            SuitecrmPermiso::flushCachePermisos();

            return;
        }

        $this->asignarRolesMenu($grupoId, $rolesGrupo);
        $this->asignarRolesMenu($configId, $rolesGrupo);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $configId = $this->resolverMenuConfiguracionId();

        foreach (self::MOVER as $url => [$moduloKey, $nombre, $orden, $padreId, $padreUrl, $padreNombre, $ordenOriginal]) {
            $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($menuId === 0) {
                continue;
            }
            $destinoPadre = $this->resolverPadreOriginal($padreId, $padreUrl, $padreNombre, $configId);
            if ($destinoPadre <= 0) {
                continue;
            }
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $destinoPadre,
                'orden' => $ordenOriginal,
                'updated_at' => now(),
            ]);
        }

        foreach (self::CREAR as $url => [$moduloKey, $nombre, $orden, $icono, $slugs, $urlProceso]) {
            $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            $menuProcesoId = (int) (DB::table('menu')->where('url', $urlProceso)->value('id') ?? 0);
            if ($menuProcesoId > 0) {
                DB::table('permiso')->whereIn('slug', $slugs)->update([
                    'menu_id' => $menuProcesoId,
                    'updated_at' => now(),
                ]);
            }
            if ($menuId > 0) {
                DB::table('menu_rol')->where('menu_id', $menuId)->delete();
                DB::table('menu')->where('id', $menuId)->delete();
            }
        }

        $grupoId = (int) (DB::table('menu')
            ->where(function ($q) use ($configId) {
                $q->where('url', self::GRUPO_URL)
                    ->orWhere(function ($q2) use ($configId) {
                        $q2->where('menu_id', $configId)->where('nombre', self::GRUPO_NOMBRE);
                    });
            })
            ->value('id') ?? 0);

        if ($grupoId > 0) {
            $moduloIds = DB::table('menu')->where('menu_id', $grupoId)->pluck('id');
            if ($moduloIds->isNotEmpty()) {
                DB::table('menu_rol')->whereIn('menu_id', $moduloIds)->delete();
                DB::table('menu')->whereIn('id', $moduloIds)->delete();
            }
            DB::table('menu_rol')->where('menu_id', $grupoId)->delete();
            DB::table('menu')->where('id', $grupoId)->delete();
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

    private function siguienteOrden(int $padreId): int
    {
        return (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
    }

    private function asegurarNodo(int $padreId, string $nombre, string $url, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', $padreId)
            ->where(function ($q) use ($nombre, $url) {
                $q->where('url', $url)->orWhere('nombre', $nombre);
            })
            ->value('id') ?? 0);

        if ($id === 0) {
            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $padreId,
                'nombre' => $nombre,
                'url' => $url,
                'orden' => $orden,
                'icono' => $icono,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $id)->update([
            'menu_id' => $padreId,
            'nombre' => $nombre,
            'url' => $url,
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function asegurarHoja(int $padreId, string $nombre, string $url, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        if ($id === 0) {
            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $padreId,
                'nombre' => $nombre,
                'url' => $url,
                'orden' => $orden,
                'icono' => $icono,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $id)->update([
            'menu_id' => $padreId,
            'nombre' => $nombre,
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ]);

        return $id;
    }

    /** @param  \Illuminate\Support\Collection<int, int|string>  $rolIds */
    private function asignarRolesMenu(int $menuId, $rolIds): void
    {
        if ($menuId <= 0) {
            return;
        }
        foreach ($rolIds as $rolId) {
            $rolId = (int) $rolId;
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }
    }

    /** @param  list<string>  $slugs */
    private function rolesConAlgunPermiso(array $slugs)
    {
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isEmpty()) {
            return collect();
        }

        return DB::table('permiso_rol')
            ->whereIn('permiso_id', $permisoIds)
            ->pluck('rol_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function rolesDelMenuUrl(string $url)
    {
        $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        if ($menuId <= 0) {
            return collect();
        }

        return DB::table('menu_rol')
            ->where('menu_id', $menuId)
            ->pluck('rol_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function eliminarNodoSiVacio(int $menuId): void
    {
        if ($menuId <= 0) {
            return;
        }
        if (DB::table('menu')->where('menu_id', $menuId)->exists()) {
            return;
        }
        DB::table('menu_rol')->where('menu_id', $menuId)->delete();
        DB::table('menu')->where('id', $menuId)->delete();
    }

    private function resolverPadreOriginal(?int $padreId, ?string $padreUrl, ?string $padreNombre, int $configId): int
    {
        if ($padreId !== null && $padreId > 0) {
            $existe = (int) (DB::table('menu')->where('id', $padreId)->value('id') ?? 0);
            if ($existe > 0) {
                return $existe;
            }
        }
        if ($padreUrl) {
            $id = (int) (DB::table('menu')->where('url', $padreUrl)->value('id') ?? 0);
            if ($id > 0) {
                return $id;
            }
        }
        if ($padreNombre) {
            $id = (int) (DB::table('menu')->where('nombre', $padreNombre)->value('id') ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return $configId;
    }
};

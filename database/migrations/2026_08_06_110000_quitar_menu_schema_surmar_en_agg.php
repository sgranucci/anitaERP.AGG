<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AGG: limpia menú/permisos/tablas/columnas Surmar que entraron por migraciones
 * 2026_08_05_* antes de condicionarlas a EL BIERZO.
 * Solo corre en AGG. En El Bierzo no toca nada.
 */
return new class extends Migration
{
    private const MENU_URLS = [
        'stock/recepcion-proveedor-surmar',
        'stock/movimiento-surmar',
        'stock/trazabilidad-surmar',
    ];

    private const PERMISO_SLUGS = [
        'listar-recepcion-proveedor-surmar',
        'crear-recepcion-proveedor-surmar',
        'editar-recepcion-proveedor-surmar',
        'actualizar-recepcion-proveedor-surmar',
        'confirmar-recepcion-proveedor-surmar',
        'anular-recepcion-proveedor-surmar',
        'imprimir-etiqueta-recepcion-surmar',
        'listar-movimiento-surmar',
        'crear-movimiento-surmar',
        'editar-movimiento-surmar',
        'actualizar-movimiento-surmar',
        'confirmar-movimiento-surmar',
        'anular-movimiento-surmar',
        'imprimir-etiqueta-movimiento-surmar',
        'listar-trazabilidad-surmar',
    ];

    private const COLUMNAS_RPA_SURMAR = [
        'stock_etiqueta_id',
        'piqueado_at',
        'hora_piqueo',
        'cant_pieza',
        'peso_neto',
        'peso_bruto',
        'fecha_vto',
        'certificado',
        'lote_proveedor',
    ];

    public function up(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'AGG') {
            return;
        }

        $this->quitarMenuYPermisos();
        $this->quitarSchemaSurmarSiVacio();
    }

    public function down(): void
    {
        // No reintroducir Surmar en AGG.
    }

    private function quitarMenuYPermisos(): void
    {
        $permisoIds = DB::table('permiso')
            ->whereIn('slug', self::PERMISO_SLUGS)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($permisoIds !== []) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        $menuIds = DB::table('menu')
            ->whereIn('url', self::MENU_URLS)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($menuIds !== []) {
            DB::table('menu_rol')->whereIn('menu_id', $menuIds)->delete();
            // Permisos huérfanos por menu_id (por si el slug cambió)
            $permisosMenu = DB::table('permiso')->whereIn('menu_id', $menuIds)->pluck('id');
            if ($permisosMenu->isNotEmpty()) {
                DB::table('permiso_rol')->whereIn('permiso_id', $permisosMenu)->delete();
                DB::table('permiso')->whereIn('id', $permisosMenu)->delete();
            }
            DB::table('menu')->whereIn('id', $menuIds)->delete();
        }
    }

    private function quitarSchemaSurmarSiVacio(): void
    {
        // Solo dropear tablas si no hay datos (no hubo uso Surmar en AGG).
        $tablas = ['stock_etiqueta_movimiento', 'stock_etiqueta_consumo', 'stock_etiqueta'];
        $hayDatos = false;
        foreach ($tablas as $tabla) {
            if (Schema::hasTable($tabla) && DB::table($tabla)->exists()) {
                $hayDatos = true;
                break;
            }
        }

        if (! $hayDatos) {
            if (Schema::hasColumn('recepcion_proveedor_articulo', 'stock_etiqueta_id')) {
                try {
                    Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
                        $table->dropForeign('fk_rpa_stock_etiqueta');
                    });
                } catch (\Throwable $e) {
                }
            }

            Schema::dropIfExists('stock_etiqueta_movimiento');
            Schema::dropIfExists('stock_etiqueta_consumo');
            Schema::dropIfExists('stock_etiqueta');
        }

        $columnasADropear = [];
        foreach (self::COLUMNAS_RPA_SURMAR as $col) {
            if (Schema::hasColumn('recepcion_proveedor_articulo', $col)) {
                $columnasADropear[] = $col;
            }
        }

        if ($columnasADropear === []) {
            return;
        }

        // No dropear columnas si alguna tiene valor (defensivo).
        foreach ($columnasADropear as $col) {
            if (DB::table('recepcion_proveedor_articulo')->whereNotNull($col)->exists()) {
                return;
            }
        }

        Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) use ($columnasADropear) {
            $table->dropColumn($columnasADropear);
        });
    }
};
